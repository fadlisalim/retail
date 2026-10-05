<?php

namespace App\Services\WaCampaign;

use App\Models\AssistantLead;
use App\Models\Order;
use App\Models\User;
use App\Models\WaCampaignLog;
use App\Models\WaCampaignMessage;
use App\Models\WaContact;
use App\Models\WaMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kontak campaign: normalisasi nomor Indonesia, dedupe (satu baris per nomor),
 * sinkron dari data existing (customer, pesanan, lead Kirana, WA chat), import
 * CSV, serta izin promo (consent) & opt-out. Aturan keras:
 *  - kontak hanya jadi penerima bila consent_status = opted_in (izin eksplisit + bukti);
 *  - opted_out TIDAK pernah ditimpa oleh sinkron/import — hanya admin dengan izin baru.
 */
class WaContactService
{
    public function __construct(private readonly WhatsAppService $wa) {}

    /** Normalisasi ke 62xxxxxxxxxx; null bila bukan nomor Indonesia yang masuk akal. */
    public function normalize(?string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim(substr($digits, 1), '0');
        } elseif (str_starts_with($digits, '8') && strlen($digits) >= 9 && strlen($digits) <= 13) {
            $digits = '62'.$digits; // "812…" tanpa 0/62
        }
        if (! str_starts_with($digits, '62') || strlen($digits) < 10 || strlen($digits) > 15) {
            return null;
        }
        if (! str_starts_with($digits, '628')) {
            return null; // bukan nomor seluler Indonesia (WhatsApp)
        }

        return $digits;
    }

    /**
     * Ambil/buat kontak untuk nomor. Data (nama, tag, minat, source) digabung;
     * status consent TIDAK diubah di sini kecuali kontak baru (unknown).
     */
    public function upsert(string $rawPhone, array $attrs = []): ?WaContact
    {
        $phone = $this->normalize($rawPhone);
        if (! $phone) {
            return null;
        }

        $contact = WaContact::firstOrNew(['phone' => $phone]);
        $isNew = ! $contact->exists;

        if (! empty($attrs['name']) && (blank($contact->name) || ! empty($attrs['prefer_name']))) {
            $contact->name = Str::limit(trim((string) $attrs['name']), 150, '');
        }
        if (! empty($attrs['user_id']) && ! $contact->user_id) {
            $contact->user_id = $attrs['user_id'];
        }
        if ($isNew) {
            $contact->source = $attrs['source'] ?? 'manual';
            $contact->consent_status = WaContact::CONSENT_UNKNOWN;
        }
        if (! empty($attrs['tags'])) {
            $contact->tags = $this->mergeList($contact->tags, $attrs['tags']);
        }
        if (! empty($attrs['interests'])) {
            $contact->interests = $this->mergeList($contact->interests, $attrs['interests']);
        }
        foreach (['orders_count', 'last_order_at', 'total_spent', 'notes'] as $k) {
            if (array_key_exists($k, $attrs) && $attrs[$k] !== null) {
                $contact->{$k} = $attrs[$k];
            }
        }
        $contact->save();

        return $contact;
    }

    /** Catat izin promo dengan bukti. Tidak menimpa opt-out kecuali $override (admin dengan izin baru dari customer). */
    public function optIn(WaContact $contact, string $source, string $proof, bool $override = false): bool
    {
        if ($contact->isOptedOut() && ! $override) {
            return false;
        }
        $contact->forceFill([
            'consent_status' => WaContact::CONSENT_IN,
            'consent_source' => Str::limit($source, 60, ''),
            'consent_proof' => Str::limit(trim($proof), 1000, ''),
            'consent_at' => now(),
            'opted_out_at' => null,
            'opted_out_reason' => null,
        ])->save();

        return true;
    }

    /** Izin dari form situs (checkout/daftar): buat/ambil kontak lalu opt-in dengan bukti. Tidak menghidupkan kontak yang sudah STOP. */
    public function recordConsent(?string $phone, ?string $name, ?int $userId, string $source, string $proof): ?WaContact
    {
        $contact = $this->upsert((string) $phone, ['name' => $name, 'user_id' => $userId, 'source' => $source]);
        if ($contact) {
            $this->optIn($contact, $source, $proof);
        }

        return $contact;
    }

    /** Berhenti promo: status opted_out, antrean pesan dibatalkan di semua campaign, pending di Wablas dibatalkan (bila didukung). */
    public function optOut(WaContact $contact, string $reason, ?WablasCampaignClient $client = null): int
    {
        $contact->forceFill([
            'consent_status' => WaContact::CONSENT_OUT,
            'opted_out_at' => now(),
            'opted_out_reason' => Str::limit($reason, 120, ''),
        ])->save();

        $cancelled = 0;
        $pending = WaCampaignMessage::where('contact_id', $contact->id)
            ->whereIn('status', [WaCampaignMessage::QUEUED, WaCampaignMessage::UNCERTAIN, WaCampaignMessage::ACCEPTED])
            ->get();
        foreach ($pending as $m) {
            if ($m->status === WaCampaignMessage::ACCEPTED) {
                // Sudah diterima gateway tapi mungkin masih pending di Wablas → coba batalkan.
                if ($m->wablas_id && $client && $client->cancel($m->wablas_id)) {
                    $m->forceFill(['status' => WaCampaignMessage::CANCELLED, 'error' => 'Dibatalkan di Wablas (STOP)'])->save();
                    $cancelled++;
                }

                continue;
            }
            $m->forceFill(['status' => WaCampaignMessage::CANCELLED, 'error' => 'Berhenti promo: '.$reason])->save();
            $cancelled++;
        }

        WaCampaignLog::write(null, "Kontak {$contact->phone} berhenti promo ({$reason}); {$cancelled} pesan antrean dibatalkan.", ['contact_id' => $contact->id]);

        return $cancelled;
    }

    /** Pesan masuk "STOP" / "BERHENTI" / "UNSUBSCRIBE" (tanpa teks lain) = permintaan berhenti promo. */
    public function isStopMessage(string $text): bool
    {
        $t = mb_strtolower(trim(preg_replace('/[^\pL\pN\s]/u', '', $text)));

        return in_array($t, ['stop', 'berhenti', 'unsubscribe', 'stop promo', 'berhenti promo', 'hentikan', 'stop promosi'], true);
    }

    /**
     * Sinkron dari data existing: akun customer, pesanan (nomor + minat kategori +
     * riwayat belanja), lead Kirana, dan pengirim WA chat. Consent tetap unknown.
     *
     * @return array{customers: int, orders: int, leads: int, chats: int}
     */
    public function syncFromExisting(): array
    {
        $n = ['customers' => 0, 'orders' => 0, 'leads' => 0, 'chats' => 0];

        User::where('is_staff', false)->whereNotNull('whatsapp')->orWhere(fn ($q) => $q->where('is_staff', false)->whereNotNull('phone'))
            ->select(['id', 'name', 'whatsapp', 'phone'])->chunk(200, function (Collection $users) use (&$n) {
                foreach ($users as $u) {
                    if ($this->upsert($u->whatsapp ?: $u->phone, ['name' => $u->name, 'user_id' => $u->id, 'source' => 'customer'])) {
                        $n['customers']++;
                    }
                }
            });

        Order::with(['items.product.category:id,slug'])
            ->whereNotNull('customer_phone')->where('payment_status', 'paid')
            ->select(['id', 'customer_name', 'customer_phone', 'user_id', 'grand_total', 'paid_at', 'created_at'])
            ->orderBy('id')->chunk(200, function (Collection $orders) use (&$n) {
                foreach ($orders as $o) {
                    $interests = $o->items->map(fn ($i) => $i->product?->category?->slug)->filter()->unique()->values()->all();
                    $phone = $this->normalize($o->customer_phone);
                    if (! $phone) {
                        continue;
                    }
                    $stats = Order::where('payment_status', 'paid')->where('customer_phone', 'like', '%'.substr($phone, 2))
                        ->selectRaw('count(*) as c, sum(grand_total) as t, max(coalesce(paid_at, created_at)) as l')->first();
                    if ($this->upsert($phone, [
                        'name' => $o->customer_name, 'user_id' => $o->user_id, 'source' => 'order', 'interests' => $interests,
                        'orders_count' => (int) $stats->c, 'total_spent' => (float) $stats->t, 'last_order_at' => $stats->l,
                    ])) {
                        $n['orders']++;
                    }
                }
            });

        AssistantLead::whereNotNull('phone')->chunk(200, function (Collection $leads) use (&$n) {
            foreach ($leads as $l) {
                if ($this->upsert($l->phone, ['name' => $l->name, 'source' => 'lead', 'tags' => ['lead-kirana']])) {
                    $n['leads']++;
                }
            }
        });

        WaMessage::where('direction', 'in')->select(['phone', 'name'])->distinct()->chunk(200, function (Collection $rows) use (&$n) {
            foreach ($rows as $r) {
                if ($this->upsert($r->phone, ['name' => $r->name, 'source' => 'wachat', 'tags' => ['wa-chat']])) {
                    $n['chats']++;
                }
            }
        });

        return $n;
    }

    /**
     * Import CSV. Kolom (header, urutan bebas): telepon|phone|nomor, nama|name, tag|tags,
     * minat|interests, izin|consent (ya/yes/1/true), bukti|bukti_izin|proof.
     * Baris tanpa izin "ya" → kontak dibuat/diperbarui tapi tetap belum boleh dikirimi.
     * Kontak yang sudah STOP tidak dihidupkan lagi oleh import.
     *
     * @return array{created: int, updated: int, opted_in: int, kept_out: int, invalid: int}
     */
    public function importCsv(string $csv, string $source = 'import', ?string $defaultProof = null): array
    {
        $n = ['created' => 0, 'updated' => 0, 'opted_in' => 0, 'kept_out' => 0, 'invalid' => 0];
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (! $lines || count($lines) < 2) {
            return $n;
        }
        $delimiter = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim($h, " \t\"'")), str_getcsv($lines[0], $delimiter, '"', ''));
        $col = fn (array $names) => collect($names)->map(fn ($x) => array_search($x, $header, true))->first(fn ($i) => $i !== false);
        $iPhone = $col(['telepon', 'phone', 'nomor', 'no_hp', 'hp', 'whatsapp', 'wa']);
        if ($iPhone === null) {
            return $n;
        }
        $iName = $col(['nama', 'name']);
        $iTags = $col(['tag', 'tags', 'label']);
        $iInterest = $col(['minat', 'interests', 'interest', 'produk']);
        $iConsent = $col(['izin', 'consent', 'opt_in', 'setuju']);
        $iProof = $col(['bukti', 'bukti_izin', 'proof', 'sumber_izin']);

        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, $delimiter, '"', '');
            $phone = $this->normalize($row[$iPhone] ?? null);
            if (! $phone) {
                $n['invalid']++;

                continue;
            }
            $existed = WaContact::where('phone', $phone)->exists();
            $split = fn ($v) => array_values(array_filter(array_map('trim', preg_split('/[|,\/]/', (string) $v))));
            $contact = $this->upsert($phone, [
                'name' => $iName !== null ? ($row[$iName] ?? null) : null, 'prefer_name' => true, 'source' => $source,
                'tags' => $iTags !== null ? $split($row[$iTags] ?? '') : [],
                'interests' => $iInterest !== null ? $split($row[$iInterest] ?? '') : [],
            ]);
            $n[$existed ? 'updated' : 'created']++;

            $consent = $iConsent !== null ? mb_strtolower(trim((string) ($row[$iConsent] ?? ''))) : '';
            if (in_array($consent, ['ya', 'yes', 'y', '1', 'true', 'setuju', 'izin'], true)) {
                $proof = trim((string) ($iProof !== null ? ($row[$iProof] ?? '') : '')) ?: ($defaultProof ?: 'Import CSV '.now()->format('d/m/Y'));
                if ($this->optIn($contact, 'import', $proof)) {
                    $n['opted_in']++;
                } else {
                    $n['kept_out']++; // sudah STOP → tidak dihidupkan
                }
            }
        }

        return $n;
    }

    private function mergeList(?array $current, array $add): array
    {
        $items = array_merge($current ?? [], array_map(fn ($v) => Str::limit(trim((string) $v), 60, ''), $add));

        return array_values(array_unique(array_filter($items, fn ($v) => $v !== '')));
    }
}
