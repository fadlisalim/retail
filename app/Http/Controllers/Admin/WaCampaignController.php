<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\WaCampaign;
use App\Models\WaCampaignLog;
use App\Models\WaCampaignMessage;
use App\Models\WaCampaignTemplate;
use App\Models\WaContact;
use App\Services\AuditService;
use App\Services\WaCampaign\WablasCampaignClient;
use App\Services\WaCampaign\WaCampaignService;
use App\Services\WaCampaign\WaCampaignSettings;
use App\Services\WaCampaign\WaContactService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** WhatsApp Campaign: daftar, builder, kontrol kirim, laporan per campaign. */
class WaCampaignController extends Controller
{
    public function __construct(
        private readonly WaCampaignService $service,
        private readonly WaCampaignSettings $settings,
        private readonly WablasCampaignClient $client,
        private readonly AuditService $audit,
    ) {}

    public function index(): View
    {
        $campaigns = WaCampaign::withCount('messages')->latest()->paginate(20);
        $overview = [
            'mock' => $this->client->isMock(),
            'emergency' => $this->settings->emergencyStop(),
            'within_window' => $this->settings->withinWindow(),
            'sent_today' => $this->service->sentToday(),
            'per_day' => $this->settings->get('per_day'),
            'eligible' => WaContact::eligible()->count(),
            'contacts' => WaContact::count(),
            'opted_out' => WaContact::where('consent_status', WaContact::CONSENT_OUT)->count(),
            'connection' => $this->settings->get('connection_type'),
            'window' => $this->settings->get('window_start').'–'.$this->settings->get('window_end').' '.$this->settings->get('timezone'),
            'queued' => WaCampaignMessage::whereIn('status', [WaCampaignMessage::QUEUED, WaCampaignMessage::SENDING])->count(),
        ];
        $logs = WaCampaignLog::latest('id')->take(8)->get();

        return view('admin.wa-campaign.index', compact('campaigns', 'overview', 'logs'));
    }

    public function create(): View
    {
        return view('admin.wa-campaign.form', $this->formData(new WaCampaign(['segment' => []])));
    }

    public function edit(WaCampaign $campaign): View
    {
        abort_unless($campaign->canEdit(), 403, 'Campaign yang sudah berjalan tidak bisa diubah.');

        return view('admin.wa-campaign.form', $this->formData($campaign));
    }

    public function store(Request $request): RedirectResponse
    {
        $campaign = new WaCampaign(['created_by' => $request->user()->id, 'status' => WaCampaign::DRAFT]);
        $this->fill($request, $campaign);
        $this->audit->log('wa_campaign.create', $campaign, [], ['name' => $campaign->name]);

        return $this->afterSave($request, $campaign);
    }

    public function update(Request $request, WaCampaign $campaign): RedirectResponse
    {
        abort_unless($campaign->canEdit(), 403);
        $this->fill($request, $campaign);
        $this->audit->log('wa_campaign.update', $campaign);

        return $this->afterSave($request, $campaign);
    }

    private function afterSave(Request $request, WaCampaign $campaign): RedirectResponse
    {
        $action = $request->input('action', 'draft');
        if ($action === 'start' || $action === 'schedule') {
            return $this->start($request, $campaign);
        }

        return redirect()->route('admin.wa-campaign.show', $campaign)->with('success', 'Campaign disimpan sebagai draf. Cek preview, kirim tes, lalu klik Mulai.');
    }

    public function show(Request $request, WaCampaign $campaign): View
    {
        $filter = $request->query('status');
        $messages = $campaign->messages()->with('contact')
            ->when($filter, fn ($q) => $q->where('status', $filter))
            ->orderBy('id')->paginate(50)->withQueryString();

        return view('admin.wa-campaign.show', [
            'campaign' => $campaign->load('product', 'creator'),
            'stats' => $campaign->stats(),
            'messages' => $messages,
            'filter' => $filter,
            'logs' => $campaign->logs()->latest('id')->take(30)->get(),
            'preview' => $this->service->render($campaign, new WaContact(['name' => 'Budi Santoso', 'phone' => '628xxx'])),
            'imageUrl' => $this->service->imageUrl($campaign),
            'link' => $this->service->trackedLink($campaign),
            'mock' => $this->client->isMock(),
            'emergency' => $this->settings->emergencyStop(),
            'testPhone' => $this->settings->get('test_phone'),
        ]);
    }

    /** Hitung audiens untuk filter yang dipilih (AJAX dari builder). */
    public function audience(Request $request): JsonResponse
    {
        $segment = $this->segmentFrom($request);
        $count = $this->service->audienceCount($segment);

        return response()->json([
            'count' => $count,
            'eligible_total' => WaContact::eligible()->count(),
            'sample' => $this->service->audienceQuery($segment)->take(5)->pluck('name')->map(fn ($n) => $n ?: '(tanpa nama)')->all(),
        ]);
    }

    /** Mulai sekarang / jadwalkan: bekukan audiens ke antrean. */
    public function start(Request $request, WaCampaign $campaign): RedirectResponse
    {
        if (! in_array($campaign->status, [WaCampaign::DRAFT, WaCampaign::SCHEDULED], true)) {
            return back()->with('error', 'Campaign sudah berjalan atau selesai.');
        }
        if ($this->settings->emergencyStop()) {
            return back()->with('error', 'EMERGENCY STOP aktif — matikan dulu di halaman WA Campaign.');
        }
        $at = null;
        if ($request->filled('scheduled_at')) {
            $at = CarbonImmutable::parse($request->input('scheduled_at'), $this->settings->get('timezone'))->utc();
            if ($at->isPast()) {
                $at = null;
            }
        }
        $count = $this->service->schedule($campaign, $at);
        if ($count === 0 && $campaign->audience_count === 0) {
            $campaign->forceFill(['status' => WaCampaign::DRAFT, 'scheduled_at' => null, 'started_at' => null])->save();

            return redirect()->route('admin.wa-campaign.show', $campaign)->with('error', 'Tidak ada penerima: belum ada kontak yang memberi izin promo sesuai filter. Tambahkan izin di halaman Kontak.');
        }
        $this->audit->log('wa_campaign.start', $campaign, [], ['audience' => $campaign->audience_count, 'scheduled_at' => $campaign->scheduled_at]);

        $note = $this->client->isMock() ? ' (MODE MOCK: tidak ada pesan nyata yang keluar)' : '';
        $msg = $campaign->status === WaCampaign::SCHEDULED
            ? 'Campaign dijadwalkan untuk '.$campaign->audience_count.' penerima'.$note.'.'
            : 'Campaign dimulai: '.$campaign->audience_count.' penerima dalam antrean, dikirim bertahap pada jam kirim'.$note.'.';

        return redirect()->route('admin.wa-campaign.show', $campaign)->with('success', $msg);
    }

    public function pause(WaCampaign $campaign, Request $request): RedirectResponse
    {
        $this->service->pause($campaign, 'Dijeda oleh '.$request->user()->name);
        $this->audit->log('wa_campaign.pause', $campaign);

        return back()->with('success', 'Campaign dijeda. Pesan yang sudah diterima gateway tetap diproses Wablas.');
    }

    public function resume(WaCampaign $campaign): RedirectResponse
    {
        if ($this->settings->emergencyStop()) {
            return back()->with('error', 'EMERGENCY STOP aktif — matikan dulu.');
        }
        $this->service->resume($campaign);
        $this->audit->log('wa_campaign.resume', $campaign);

        return back()->with('success', 'Campaign dilanjutkan.');
    }

    public function cancel(WaCampaign $campaign, Request $request): RedirectResponse
    {
        if (! $campaign->isActive() && $campaign->status !== WaCampaign::DRAFT) {
            return back()->with('error', 'Campaign sudah selesai/dibatalkan.');
        }
        $n = $this->service->cancel($campaign, 'Dibatalkan oleh '.$request->user()->name);
        $this->audit->log('wa_campaign.cancel', $campaign, [], ['cancelled' => $n]);

        return back()->with('success', "Campaign dibatalkan, {$n} pesan antrean dibatalkan.");
    }

    public function destroy(WaCampaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, [WaCampaign::DRAFT, WaCampaign::CANCELLED, WaCampaign::COMPLETED], true), 403);
        $this->audit->log('wa_campaign.delete', $campaign, ['name' => $campaign->name]);
        $campaign->delete();

        return redirect()->route('admin.wa-campaign.index')->with('success', 'Campaign dihapus.');
    }

    /** Pesan "tidak pasti" (timeout) / gagal → antre ulang secara manual setelah admin mengecek Wablas. */
    public function requeue(WaCampaign $campaign, WaCampaignMessage $message): RedirectResponse
    {
        abort_unless($message->campaign_id === $campaign->id, 404);
        if (! in_array($message->status, [WaCampaignMessage::UNCERTAIN, WaCampaignMessage::FAILED, WaCampaignMessage::SKIPPED], true)) {
            return back()->with('error', 'Hanya pesan gagal / tidak pasti / dilewati yang bisa diantrekan ulang.');
        }
        $message->forceFill(['status' => WaCampaignMessage::QUEUED, 'next_attempt_at' => null, 'error' => null])->save();
        if (in_array($campaign->status, [WaCampaign::COMPLETED], true)) {
            $campaign->forceFill(['status' => WaCampaign::RUNNING, 'finished_at' => null])->save();
        }
        WaCampaignLog::write($campaign->id, "Pesan ke {$message->phone} diantrekan ulang manual.", ['message_id' => $message->id]);

        return back()->with('success', 'Pesan diantrekan ulang (dikirim pada putaran berikutnya, tetap dicek izin & batas 7 hari).');
    }

    /** Kirim tes ke nomor tes dari Pengaturan — hanya nomor itu, tidak pernah ke kontak. */
    public function test(Request $request): RedirectResponse|JsonResponse
    {
        $testPhone = app(WaContactService::class)->normalize($this->settings->get('test_phone'));
        if (! $testPhone) {
            return $this->testResponse($request, false, 'Isi "Nomor tes" di Pengaturan WA Campaign dulu.');
        }
        $campaign = $request->filled('campaign_id') ? WaCampaign::findOrFail($request->integer('campaign_id')) : [
            'name' => $request->input('name', 'Tes'), 'message' => (string) $request->input('message', ''),
            'product_id' => $request->input('product_id') ?: null, 'link_url' => $request->input('link_url') ?: null,
            'utm_campaign' => $request->input('utm_campaign') ?: null, 'image_path' => $request->input('image_path') ?: null,
        ];
        if (is_array($campaign) && trim($campaign['message']) === '') {
            return $this->testResponse($request, false, 'Isi pesan dulu.');
        }
        $r = $this->service->sendTest($campaign, $testPhone);
        $this->audit->log('wa_campaign.test', is_object($campaign) ? $campaign : null, [], ['phone' => $testPhone, 'ok' => $r['ok']]);

        return $this->testResponse($request, $r['ok'], $r['ok']
            ? 'Pesan tes dikirim ke '.$testPhone.($this->client->isMock() ? ' (MODE MOCK — tidak benar-benar terkirim)' : '').'.'
            : 'Gagal: '.($r['error'] ?? 'tidak diketahui'));
    }

    public function emergency(Request $request): RedirectResponse
    {
        $on = $request->boolean('on');
        $this->service->emergencyStop($on, $request->user()->name);
        $this->audit->log('wa_campaign.emergency_'.($on ? 'on' : 'off'));

        return back()->with($on ? 'error' : 'success', $on ? 'EMERGENCY STOP aktif: semua pengiriman dihentikan dan campaign aktif dijeda.' : 'Emergency stop dimatikan. Lanjutkan campaign satu per satu bila perlu.');
    }

    // ------------------------------------------------------------- helpers

    private function formData(WaCampaign $campaign): array
    {
        return [
            'campaign' => $campaign,
            'templates' => WaCampaignTemplate::orderBy('name')->get(),
            'products' => Product::where('status', 'published')->orderBy('name')->get(['id', 'name', 'slug', 'main_image_path', 'price', 'sale_price']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'tags' => WaContact::whereNotNull('tags')->pluck('tags')->flatten()->unique()->sort()->values()->all(),
            'sources' => ['customer' => 'Akun customer', 'order' => 'Pernah pesan', 'lead' => 'Lead Kirana', 'wachat' => 'WA chat', 'import' => 'Import CSV', 'manual' => 'Manual', 'checkout' => 'Izin saat checkout', 'register' => 'Izin saat daftar'],
            'eligibleTotal' => WaContact::eligible()->count(),
            'footer' => $this->settings->footer(),
            'testPhone' => $this->settings->get('test_phone'),
            'mock' => $this->client->isMock(),
            'timezone' => $this->settings->get('timezone'),
        ];
    }

    private function fill(Request $request, WaCampaign $campaign): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'product_id' => ['nullable', 'exists:products,id'],
            'link_url' => ['nullable', 'url', 'max:500'],
            'utm_campaign' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'save_template' => ['nullable', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:120'],
        ]);

        $campaign->fill([
            'name' => $data['name'],
            'message' => $data['message'],
            'product_id' => $data['product_id'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'utm_campaign' => ($data['utm_campaign'] ?? null) ?: Str::slug($data['name']),
            'segment' => $this->segmentFrom($request),
        ]);
        if ($request->boolean('remove_image')) {
            $campaign->image_path = null;
        }
        if ($request->hasFile('image')) {
            $campaign->image_path = $request->file('image')->store('wa-campaign', 'public');
        }
        $campaign->audience_count = $this->service->audienceCount($campaign->segment);
        $campaign->save();

        if ($request->boolean('save_template')) {
            WaCampaignTemplate::create([
                'name' => $data['template_name'] ?: $campaign->name, 'body' => $campaign->message,
                'image_path' => $campaign->image_path, 'created_by' => $request->user()->id,
            ]);
        }
    }

    private function segmentFrom(Request $request): array
    {
        $list = fn ($v) => array_values(array_filter(array_map('trim', (array) $v)));

        return array_filter([
            'tags' => $list($request->input('tags', [])),
            'interests' => $list($request->input('interests', [])),
            'sources' => $list($request->input('sources', [])),
            'purchase' => in_array($request->input('purchase'), ['buyers', 'never'], true) ? $request->input('purchase') : null,
            'last_order_days' => (int) $request->input('last_order_days') ?: null,
            'contact_ids' => array_map('intval', $list($request->input('contact_ids', []))),
        ]);
    }

    private function testResponse(Request $request, bool $ok, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
