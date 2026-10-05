<?php

namespace App\Services\WaCampaign;

use App\Models\Product;
use App\Models\WaCampaign;
use App\Models\WaCampaignLog;
use App\Models\WaCampaignMessage;
use App\Models\WaContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Inti campaign: audiens (hanya kontak ber-izin), antrean persisten per penerima,
 * personalisasi + UTM, dan dispatcher terkendali (jam kirim, rate/menit, kuota
 * harian, 1 promo / 7 hari lintas campaign, retry terbatas, timeout = uncertain
 * tanpa kirim ulang otomatis, jeda otomatis saat kegagalan beruntun / device
 * putus, emergency stop global).
 */
class WaCampaignService
{
    public function __construct(
        private readonly WaCampaignSettings $settings,
        private readonly WablasCampaignClient $client,
    ) {}

    // ---------------------------------------------------------------- audiens

    /** Query kontak sesuai filter segmen; SELALU dibatasi ke kontak yang mengizinkan promo. */
    public function audienceQuery(array $segment): Builder
    {
        $q = WaContact::query()->eligible();

        if (! empty($segment['tags'])) {
            $q->where(function (Builder $w) use ($segment) {
                foreach ((array) $segment['tags'] as $tag) {
                    $w->orWhereJsonContains('tags', $tag);
                }
            });
        }
        if (! empty($segment['interests'])) {
            $q->where(function (Builder $w) use ($segment) {
                foreach ((array) $segment['interests'] as $slug) {
                    $w->orWhereJsonContains('interests', $slug);
                }
            });
        }
        if (! empty($segment['sources'])) {
            $q->whereIn('source', (array) $segment['sources']);
        }
        match ($segment['purchase'] ?? 'any') {
            'buyers' => $q->where('orders_count', '>', 0),
            'never' => $q->where('orders_count', 0),
            default => null,
        };
        if (! empty($segment['last_order_days'])) {
            $q->where('last_order_at', '>=', now()->subDays((int) $segment['last_order_days']));
        }
        if (! empty($segment['contact_ids'])) {
            $q->whereIn('id', array_map('intval', (array) $segment['contact_ids']));
        }

        return $q;
    }

    public function audienceCount(array $segment): int
    {
        return $this->audienceQuery($segment)->count();
    }

    // -------------------------------------------------------------- pesan

    /** Link dengan UTM (utm_source=whatsapp, utm_medium=campaign, utm_campaign=slug). */
    public function trackedLink(WaCampaign $campaign): ?string
    {
        $url = $campaign->link_url ?: ($campaign->product ? route('products.show', $campaign->product->slug) : null);
        if (! $url) {
            return null;
        }
        $utm = http_build_query([
            'utm_source' => 'whatsapp',
            'utm_medium' => 'campaign',
            'utm_campaign' => $campaign->utm_campaign ?: Str::slug($campaign->name),
        ]);

        return $url.(str_contains($url, '?') ? '&' : '?').$utm;
    }

    /** Render pesan untuk satu kontak: {nama}, {link}, {produk}, {harga} + footer STOP. */
    public function render(WaCampaign $campaign, WaContact $contact): string
    {
        $link = $this->trackedLink($campaign) ?? '';
        $product = $campaign->product;
        $body = strtr($campaign->message, [
            '{nama}' => $contact->firstName(),
            '{nama_lengkap}' => trim((string) $contact->name) ?: 'Kak',
            '{link}' => $link,
            '{produk}' => $product?->name ?? '',
            '{harga}' => $product ? rupiah($product->effectivePrice()) : '',
        ]);
        $body = trim($body);
        if ($link !== '' && ! str_contains($campaign->message, '{link}')) {
            $body .= "\n\n".$link;
        }
        $footer = $this->settings->footer();
        if ($footer !== '' && ! str_contains(mb_strtolower($body), 'stop')) {
            $body .= "\n\n".$footer;
        }

        return $body;
    }

    /** URL gambar publik untuk Wablas (upload campaign atau foto utama produk). */
    public function imageUrl(WaCampaign $campaign): ?string
    {
        $path = $campaign->image_path ?: $campaign->product?->main_image_path;
        if (! $path) {
            return null;
        }
        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    // ------------------------------------------------------------- lifecycle

    /** Bekukan audiens ke antrean (1 baris / kontak) dan jadwalkan / mulai. */
    public function schedule(WaCampaign $campaign, ?\DateTimeInterface $at = null): int
    {
        $count = 0;
        $this->audienceQuery($campaign->segment ?? [])->select(['id', 'phone'])->orderBy('id')->chunk(500, function ($contacts) use ($campaign, &$count) {
            $rows = [];
            foreach ($contacts as $c) {
                $rows[] = ['campaign_id' => $campaign->id, 'contact_id' => $c->id, 'phone' => $c->phone, 'status' => WaCampaignMessage::QUEUED, 'created_at' => now(), 'updated_at' => now()];
            }
            // insertOrIgnore + unique(campaign, contact) = tidak pernah ada baris ganda.
            $count += WaCampaignMessage::insertOrIgnore($rows);
        });

        $campaign->forceFill([
            'audience_count' => $campaign->messages()->count(),
            'scheduled_at' => $at ?? now(),
            'status' => $at && $at > now() ? WaCampaign::SCHEDULED : WaCampaign::RUNNING,
            'started_at' => $at && $at > now() ? null : now(),
            'pause_reason' => null,
            'consecutive_failures' => 0,
        ])->save();
        WaCampaignLog::write($campaign->id, 'Campaign '.($campaign->status === WaCampaign::SCHEDULED ? 'dijadwalkan '.$campaign->scheduled_at->timezone($this->settings->get('timezone'))->format('d/m/Y H:i') : 'dimulai').", {$campaign->audience_count} penerima dalam antrean.");

        return $count;
    }

    public function pause(WaCampaign $campaign, string $reason, string $level = 'info'): void
    {
        if (! in_array($campaign->status, [WaCampaign::RUNNING, WaCampaign::SCHEDULED], true)) {
            return;
        }
        $campaign->forceFill(['status' => WaCampaign::PAUSED, 'pause_reason' => Str::limit($reason, 200, '')])->save();
        WaCampaignLog::write($campaign->id, 'Dijeda: '.$reason, [], $level);
    }

    public function resume(WaCampaign $campaign): void
    {
        if ($campaign->status !== WaCampaign::PAUSED) {
            return;
        }
        $campaign->forceFill(['status' => WaCampaign::RUNNING, 'pause_reason' => null, 'consecutive_failures' => 0, 'started_at' => $campaign->started_at ?? now()])->save();
        WaCampaignLog::write($campaign->id, 'Dilanjutkan.');
    }

    /** Batalkan: semua antrean → cancelled; pesan yang sudah diterima gateway dicoba dibatalkan di Wablas. */
    public function cancel(WaCampaign $campaign, string $reason = 'Dibatalkan admin'): int
    {
        $n = $campaign->messages()->whereIn('status', [WaCampaignMessage::QUEUED, WaCampaignMessage::UNCERTAIN])
            ->update(['status' => WaCampaignMessage::CANCELLED, 'error' => $reason, 'updated_at' => now()]);
        foreach ($campaign->messages()->where('status', WaCampaignMessage::ACCEPTED)->whereNotNull('wablas_id')->get() as $m) {
            if ($this->client->cancel($m->wablas_id)) {
                $m->forceFill(['status' => WaCampaignMessage::CANCELLED, 'error' => $reason.' (dibatalkan di Wablas)'])->save();
                $n++;
            }
        }
        $campaign->forceFill(['status' => WaCampaign::CANCELLED, 'finished_at' => now(), 'pause_reason' => null])->save();
        WaCampaignLog::write($campaign->id, "Dibatalkan ({$reason}); {$n} pesan antrean dibatalkan.", [], 'warning');

        return $n;
    }

    /** Emergency stop global: hentikan semua pengiriman + jeda semua campaign aktif. */
    public function emergencyStop(bool $on, ?string $by = null): void
    {
        $this->settings->set('emergency_stop', $on);
        if ($on) {
            foreach (WaCampaign::whereIn('status', [WaCampaign::RUNNING, WaCampaign::SCHEDULED])->get() as $c) {
                $this->pause($c, 'EMERGENCY STOP oleh '.($by ?: 'admin'), 'warning');
            }
        }
        WaCampaignLog::write(null, 'Emergency stop '.($on ? 'AKTIF' : 'dimatikan').' oleh '.($by ?: 'admin'), [], 'warning');
    }

    // -------------------------------------------------------------- dispatch

    /**
     * Satu putaran dispatcher (dipanggil scheduler tiap menit). Mengembalikan
     * ringkasan ['sent'=>n,'skipped'=>n,'failed'=>n,'uncertain'=>n,'reason'=>?string].
     */
    public function dispatch(?int $limit = null): array
    {
        $out = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'uncertain' => 0, 'reason' => null];

        if ($this->settings->emergencyStop()) {
            $out['reason'] = 'emergency_stop';

            return $out;
        }

        // Campaign terjadwal yang waktunya tiba → berjalan.
        WaCampaign::where('status', WaCampaign::SCHEDULED)->where('scheduled_at', '<=', now())
            ->get()->each(fn ($c) => $c->forceFill(['status' => WaCampaign::RUNNING, 'started_at' => $c->started_at ?? now()])->save());

        if (! $this->settings->withinWindow()) {
            $out['reason'] = 'outside_window';

            return $out;
        }

        $lock = Cache::lock('wa-campaign:dispatch', 120);
        if (! $lock->get()) {
            $out['reason'] = 'locked';

            return $out;
        }

        try {
            $dailyLeft = $this->settings->get('per_day') - $this->sentToday();
            $budget = min($limit ?? $this->settings->get('per_minute'), max(0, $dailyLeft));
            if ($budget <= 0) {
                $out['reason'] = 'daily_limit';

                return $out;
            }

            // Device putus → jeda semua campaign berjalan (cek sekali per putaran, 1x per 5 menit).
            if (! $this->client->isMock() && Cache::add('wa-campaign:device-check', 1, 300)) {
                $info = $this->client->deviceInfo();
                if ($info['connected'] === false) {
                    foreach (WaCampaign::where('status', WaCampaign::RUNNING)->get() as $c) {
                        $this->pause($c, 'Device Wablas tidak terhubung ('.($info['status'] ?? '?').') — scan ulang QR lalu lanjutkan.', 'error');
                    }
                    $out['reason'] = 'device_disconnected';

                    return $out;
                }
            }

            $campaigns = WaCampaign::where('status', WaCampaign::RUNNING)->orderBy('started_at')->get();
            foreach ($campaigns as $campaign) {
                while ($budget > 0) {
                    $message = $this->claimNext($campaign);
                    if (! $message) {
                        break;
                    }
                    $result = $this->sendOne($campaign, $message);
                    $out[$result]++;
                    if ($result === 'sent' || $result === 'uncertain' || $result === 'failed') {
                        $budget--;
                        // Jeda antar pesan supaya tidak menyembur (rate = per_minute).
                        if ($budget > 0 && ! app()->runningUnitTests()) {
                            usleep((int) (60_000_000 / max(1, $this->settings->get('per_minute'))));
                        }
                    }
                    if ($campaign->fresh()->status !== WaCampaign::RUNNING) {
                        break; // dijeda otomatis karena kegagalan beruntun
                    }
                }
                $this->finishIfDone($campaign->fresh());
            }
        } finally {
            $lock->release();
        }

        return $out;
    }

    /** Ambil satu baris antrean secara atomik (queued → sending) agar dua proses tidak mengirim pesan yang sama. */
    private function claimNext(WaCampaign $campaign): ?WaCampaignMessage
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = $campaign->messages()
                ->where(fn ($q) => $q->where('status', WaCampaignMessage::QUEUED)
                    ->orWhere(fn ($r) => $r->where('status', WaCampaignMessage::FAILED)->whereNotNull('next_attempt_at')->where('next_attempt_at', '<=', now())))
                ->orderBy('id')->first();
            if (! $candidate) {
                return null;
            }
            $claimed = WaCampaignMessage::whereKey($candidate->id)->where('status', $candidate->status)
                ->update(['status' => WaCampaignMessage::SENDING, 'updated_at' => now()]);
            if ($claimed === 1) {
                return $candidate->fresh();
            }
        }

        return null;
    }

    /** Kirim satu pesan; mengembalikan 'sent' | 'skipped' | 'failed' | 'uncertain'. */
    private function sendOne(WaCampaign $campaign, WaCampaignMessage $message): string
    {
        $contact = $message->contact;

        // Periksa ulang izin & batas 7 hari TEPAT sebelum kirim (bisa berubah sejak campaign dibuat).
        $gap = $this->settings->get('min_gap_days');
        $skip = match (true) {
            ! $contact => 'Kontak sudah dihapus',
            ! $contact->isOptedIn() => $contact->isOptedOut() ? 'Berhenti promo (STOP)' : 'Belum ada izin promo',
            $gap > 0 && $contact->last_promo_at && $contact->last_promo_at->gt(now()->subDays($gap)) => "Sudah menerima promo < {$gap} hari",
            default => null,
        };
        if ($skip) {
            $message->forceFill(['status' => WaCampaignMessage::SKIPPED, 'error' => $skip])->save();

            return 'skipped';
        }

        $text = $this->render($campaign, $contact);
        $image = $this->imageUrl($campaign);
        $message->forceFill(['rendered_message' => $text, 'attempts' => $message->attempts + 1])->save();

        $result = $image ? $this->client->sendImage($contact->phone, $image, $text) : $this->client->sendText($contact->phone, $text);

        if ($result['ok']) {
            $message->forceFill(['status' => WaCampaignMessage::ACCEPTED, 'wablas_id' => $result['id'], 'sent_at' => now(), 'error' => null, 'next_attempt_at' => null])->save();
            $contact->forceFill(['last_promo_at' => now()])->save();
            $campaign->forceFill(['consecutive_failures' => 0])->save();

            return 'sent';
        }

        if ($result['uncertain']) {
            // Hasil tidak diketahui (timeout) — jangan kirim ulang otomatis; admin memutuskan setelah cek Wablas.
            $message->forceFill(['status' => WaCampaignMessage::UNCERTAIN, 'error' => $result['error'], 'next_attempt_at' => null])->save();
            $contact->forceFill(['last_promo_at' => now()])->save(); // anggap mungkin terkirim → tetap hitung jeda 7 hari
            WaCampaignLog::write($campaign->id, "Timeout ke {$contact->phone}: hasil tidak pasti, tidak dikirim ulang otomatis.", ['message_id' => $message->id], 'warning');
            $this->noteFailure($campaign);

            return 'uncertain';
        }

        $retry = $result['retryable'] && $message->attempts < $this->settings->get('max_attempts');
        $message->forceFill([
            'status' => WaCampaignMessage::FAILED,
            'error' => $result['error'],
            'failed_at' => now(),
            'next_attempt_at' => $retry ? now()->addMinutes(5 * $message->attempts) : null, // backoff 5, 10 menit…
        ])->save();
        $this->noteFailure($campaign);

        return 'failed';
    }

    private function noteFailure(WaCampaign $campaign): void
    {
        $campaign->increment('consecutive_failures');
        $limit = $this->settings->get('failure_pause_after');
        if ($limit > 0 && $campaign->fresh()->consecutive_failures >= $limit) {
            $this->pause($campaign, "{$limit} kegagalan beruntun — periksa device/kuota Wablas, lalu lanjutkan.", 'error');
        }
    }

    private function finishIfDone(WaCampaign $campaign): void
    {
        if ($campaign->status !== WaCampaign::RUNNING) {
            return;
        }
        $pending = $campaign->messages()->where(fn ($q) => $q->whereIn('status', [WaCampaignMessage::QUEUED, WaCampaignMessage::SENDING])
            ->orWhere(fn ($r) => $r->where('status', WaCampaignMessage::FAILED)->whereNotNull('next_attempt_at')))->exists();
        if (! $pending) {
            $campaign->forceFill(['status' => WaCampaign::COMPLETED, 'finished_at' => now()])->save();
            WaCampaignLog::write($campaign->id, 'Selesai: semua antrean diproses.', $campaign->stats());
        }
    }

    /** Jumlah pesan promo yang sudah dikirim (diterima gateway/uncertain) hari ini, semua campaign. */
    public function sentToday(): int
    {
        $start = $this->settings->now()->startOfDay()->utc();

        return WaCampaignMessage::where('sent_at', '>=', $start)->orWhere(fn ($q) => $q->where('status', WaCampaignMessage::UNCERTAIN)->where('updated_at', '>=', $start))->count();
    }

    // ---------------------------------------------------------------- webhook

    /** Perbarui status pesan dari webhook Wablas (ack/status). Idempotent; status tidak pernah mundur. */
    public function applyStatus(string $wablasId, string $status, ?string $note = null): bool
    {
        $message = WaCampaignMessage::where('wablas_id', $wablasId)->first();
        if (! $message) {
            return false;
        }
        $status = strtolower($status);
        $map = ['sent' => 'sent', 'delivered' => 'delivered', 'read' => 'read', 'played' => 'read', 'cancel' => 'cancelled', 'cancelled' => 'cancelled', 'reject' => 'failed', 'rejected' => 'failed', 'failed' => 'failed', 'error' => 'failed'];
        $new = $map[$status] ?? null;
        if (! $new) {
            return false;
        }
        if (in_array($new, ['sent', 'delivered', 'read'], true)) {
            if (WaCampaignMessage::rank($new) <= WaCampaignMessage::rank($message->status) && $message->status !== WaCampaignMessage::UNCERTAIN) {
                return true; // sudah lebih maju / duplikat
            }
            $message->forceFill(['status' => $new, 'sent_at' => $message->sent_at ?? now(), 'delivered_at' => in_array($new, ['delivered', 'read'], true) ? ($message->delivered_at ?? now()) : $message->delivered_at, 'read_at' => $new === 'read' ? ($message->read_at ?? now()) : $message->read_at])->save();

            return true;
        }
        if (in_array($message->status, ['delivered', 'read'], true)) {
            return true; // sudah sampai; laporan gagal/batal yang terlambat diabaikan
        }
        $message->forceFill(['status' => $new, 'error' => $note ? Str::limit($note, 500, '') : ($new === 'failed' ? 'Ditolak/gagal menurut laporan Wablas' : 'Dibatalkan di Wablas'), 'failed_at' => $new === 'failed' ? now() : $message->failed_at])->save();

        return true;
    }

    /** Catat balasan dari kontak yang baru menerima promo (≤ 7 hari). */
    public function noteReply(string $phone): void
    {
        $message = WaCampaignMessage::where('phone', $phone)->whereNotNull('sent_at')->where('sent_at', '>=', now()->subDays(7))->latest('sent_at')->first();
        $message?->increment('reply_count');
    }

    /** Kirim pesan tes ke nomor tes (Pengaturan) — tidak masuk antrean/statistik, tidak menyentuh kontak. */
    public function sendTest(WaCampaign|array $campaign, string $phone): array
    {
        $c = $campaign instanceof WaCampaign ? $campaign : new WaCampaign($campaign);
        if (! $c->relationLoaded('product') && ! empty($c->product_id)) {
            $c->setRelation('product', Product::find($c->product_id));
        }
        $contact = new WaContact(['phone' => $phone, 'name' => 'Tes']);
        $text = $this->render($c, $contact);
        $image = $this->imageUrl($c);

        return ($image ? $this->client->sendImage($phone, $image, $text) : $this->client->sendText($phone, $text)) + ['rendered' => $text, 'image' => $image];
    }
}
