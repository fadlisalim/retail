<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WaContact;
use App\Services\AuditService;
use App\Services\WaCampaign\WablasCampaignClient;
use App\Services\WaCampaign\WaContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Kontak WA Campaign: daftar + filter, import CSV, sinkron data existing, izin promo & STOP. */
class WaContactController extends Controller
{
    public function __construct(private readonly WaContactService $contacts, private readonly AuditService $audit) {}

    public function index(Request $request): View
    {
        $q = WaContact::query()->withCount('messages');
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $term).'%'));
        }
        if ($request->filled('consent')) {
            $q->where('consent_status', $request->input('consent'));
        }
        if ($request->filled('tag')) {
            $q->whereJsonContains('tags', $request->input('tag'));
        }
        if ($request->filled('source')) {
            $q->where('source', $request->input('source'));
        }

        return view('admin.wa-campaign.contacts', [
            'contacts' => $q->latest('updated_at')->paginate(30)->withQueryString(),
            'counts' => [
                'all' => WaContact::count(),
                'in' => WaContact::where('consent_status', WaContact::CONSENT_IN)->count(),
                'out' => WaContact::where('consent_status', WaContact::CONSENT_OUT)->count(),
                'unknown' => WaContact::where('consent_status', WaContact::CONSENT_UNKNOWN)->count(),
            ],
            'tags' => WaContact::whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values()->all(),
            'sources' => WaContact::select('source')->distinct()->pluck('source')->all(),
        ]);
    }

    /** Tambah kontak manual (izin hanya bila ada bukti). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:150'],
            'tags' => ['nullable', 'string', 'max:300'],
            'consent' => ['nullable', 'boolean'],
            'proof' => ['required_if:consent,1', 'nullable', 'string', 'max:1000'],
        ]);
        $contact = $this->contacts->upsert($data['phone'], [
            'name' => $data['name'] ?? null, 'prefer_name' => true, 'source' => 'manual',
            'tags' => array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))),
        ]);
        if (! $contact) {
            return back()->with('error', 'Nomor tidak valid (harus nomor WhatsApp Indonesia, mis. 0812… atau 628…).');
        }
        if ($request->boolean('consent')) {
            $ok = $this->contacts->optIn($contact, 'manual', (string) $data['proof'].' — dicatat '.$request->user()->name);
            if (! $ok) {
                return back()->with('error', 'Kontak ini sudah membalas STOP. Izin baru hanya bisa dicatat lewat tombol "Izin lagi" dengan bukti baru.');
            }
        }
        $this->audit->log('wa_contact.create', $contact, [], ['phone' => $contact->phone, 'consent' => $contact->consent_status]);

        return back()->with('success', 'Kontak '.$contact->phone.' disimpan ('.$contact->consentLabel().').');
    }

    public function update(Request $request, WaContact $contact): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'tags' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'consent_action' => ['nullable', 'in:opt_in,opt_out,reopt_in'],
            'proof' => ['required_if:consent_action,opt_in', 'required_if:consent_action,reopt_in', 'nullable', 'string', 'max:1000'],
        ]);
        $old = ['consent' => $contact->consent_status];
        $contact->forceFill([
            'name' => $data['name'] ?? $contact->name,
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? ''))))),
            'notes' => $data['notes'] ?? $contact->notes,
        ])->save();

        $msg = 'Kontak diperbarui.';
        switch ($data['consent_action'] ?? null) {
            case 'opt_in':
                if (! $this->contacts->optIn($contact, 'manual', $data['proof'].' — dicatat '.$request->user()->name)) {
                    return back()->with('error', 'Kontak ini sudah STOP; pakai "Izin lagi" dengan bukti izin baru dari customer.');
                }
                $msg = 'Izin promo dicatat.';
                break;
            case 'reopt_in':
                // Hanya bila customer sendiri meminta lagi — bukti wajib.
                $this->contacts->optIn($contact, 'manual', 'IZIN ULANG setelah STOP: '.$data['proof'].' — dicatat '.$request->user()->name, override: true);
                $msg = 'Izin promo dicatat ulang (bukti tersimpan).';
                break;
            case 'opt_out':
                $this->contacts->optOut($contact, 'Admin: '.$request->user()->name, app(WablasCampaignClient::class));
                $msg = 'Kontak dikeluarkan dari promo; antreannya dibatalkan.';
                break;
        }
        $this->audit->log('wa_contact.update', $contact, $old, ['consent' => $contact->fresh()->consent_status]);

        return back()->with('success', $msg);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'proof' => ['nullable', 'string', 'max:300'],
        ]);
        $n = $this->contacts->importCsv((string) file_get_contents($request->file('file')->getRealPath()), 'import', $request->input('proof'));
        $this->audit->log('wa_contact.import', null, [], $n);

        return back()->with('success', "Import selesai: {$n['created']} kontak baru, {$n['updated']} diperbarui, {$n['opted_in']} izin promo dicatat, {$n['kept_out']} tetap STOP (tidak dihidupkan), {$n['invalid']} nomor tidak valid.");
    }

    public function sync(): RedirectResponse
    {
        $n = $this->contacts->syncFromExisting();
        $this->audit->log('wa_contact.sync', null, [], $n);

        return back()->with('success', "Sinkron selesai: customer {$n['customers']}, pesanan {$n['orders']}, lead {$n['leads']}, WA chat {$n['chats']}. Izin promo TIDAK diubah — kontak tanpa izin tidak akan dikirimi.");
    }
}
