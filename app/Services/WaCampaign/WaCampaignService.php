<?php

namespace App\Services\WaCampaign;

use App\Models\Product;
use App\Models\WaCampaign;
use App\Models\WaCampaignLog;
use App\Models\WaCampaignMessage;
use App\Models\WaContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    /**
     * Query kontak sesuai filter segmen. Promo: SELALU dibatasi ke kontak yang mengizinkan
     * promo. Campaign minta izin: kontak yang belum izin, belum STOP, belum pernah ditanya.
     */
    public function audienceQuery(array $segment): Builder
    {
        $q = ($segment['kind'] ?? 'promo') === 'permission' ? WaContact::query()->askable() : WaContact::query()->eligible();

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

    /** Link pendek untuk isi pesan: energi.click/go/<utm_campaign> → redirect ke link lengkap ber-UTM. */
    public function shortLink(WaCampaign $campaign): ?string
    {
        if (! $this->trackedLink($campaign)) {
            return null;
        }

        return route('wa-campaign.go', $campaign->utm_campaign ?: Str::slug($campaign->name));
    }

    /** Render pesan untuk satu kontak: {nama}, {sales}, {link}, {produk}, {harga} + footer STOP. Pesan izin: tanpa link. */
    public function render(WaCampaign $campaign, WaContact $contact, int $variant = 0): string
    {
        $variants = $campaign->variants();
        $template = $variants[$variant] ?? $variants[0] ?? (string) $campaign->message;
        $link = $campaign->isPermission() ? '' : ($this->shortLink($campaign) ?? '');
        $product = $campaign->product;
        $body = strtr($template, [
            '{nama}' => $contact->firstName(),
            '{nama_lengkap}' => trim((string) $contact->name) ?: 'Kak',
            '{sales}' => $this->settings->salesName($contact->salesCode()),
            '{link}' => $link,
            '{produk}' => $product?->name ?? '',
            '{harga}' => $product ? rupiah($product->effectivePrice()) : '',
        ]);
        $body = trim($body);
        if ($link !== '' && ! str_contains($template, '{link}')) {
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
        if ($campaign->isPermission()) {
            return null; // pesan izin: teks saja, foto dikirim setelah dibalas YA
        }
        $path = $campaign->image_path ?: $campaign->product?->main_image_path;
        if (! $path) {
            return null;
        }
        $path = $this->jpegCopy($path);
        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http') ? $url : url($url);
    }

    /**
     * Wablas hanya menerima JPG/PNG untuk send-image, sedangkan foto produk di situs
     * disimpan .webp. Buat salinan JPEG sekali (products/wa/<hash>.jpg) dan pakai itu.
     */
    private function jpegCopy(string $path): string
    {
        if (! str_ends_with(strtolower($path), '.webp')) {
            return $path;
        }
        $disk = Storage::disk('public');
        $copy = 'products/wa/'.substr(sha1($path), 0, 16).'.jpg';
        if ($disk->exists($copy) || ! $disk->exists($path) || ! \function_exists('imagecreatefromwebp')) {
            return $disk->exists($copy) ? $copy : $path;
        }
        $img = @imagecreatefromwebp($disk->path($path));
        if (! $img) {
            return $path;
        }
        $canvas = imagecreatetruecolor(imagesx($img), imagesy($img));
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($canvas, null, 88);
        $disk->put($copy, (string) ob_get_clean());
        imagedestroy($img);
        imagedestroy($canvas);

        return $copy;
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
        $campaign->forceFill(['status' => WaCampaign::RUNNING, 'pause_reason' => null, 'consecutive_failures' => 0, 'started_at' => $campaign->started_at ?? now(), 'stop_window_from' => now()])->save();
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
    public const REASON_LABELS = [
        'emergency_stop' => 'EMERGENCY STOP aktif — tidak ada yang dikirim.',
        'outside_window' => 'Di luar jam/hari kirim.',
        'daily_limit' => 'Kuota harian tercapai.',
        'gap_wait' => 'Menunggu jeda antar pesan.',
        'device_disconnected' => 'Device Wablas tidak terhubung — campaign dijeda.',
        'locked' => 'Putaran sebelumnya masih berjalan.',
    ];

    public function dispatch(?int $limit = null): array
    {
        $out = $this->runDispatch($limit);
        // Detak jantung dispatcher: halaman campaign memberi tahu bila scheduler tidak jalan.
        Cache::put('wa-campaign:last-dispatch', ['at' => now()->toIso8601String(), 'reason' => $out['reason'], 'sent' => $out['sent']], 86400);

        return $out;
    }

    /** @return array{at: ?Carbon, reason: ?string, sent: int} */
    public function lastDispatch(): array
    {
        $h = Cache::get('wa-campaign:last-dispatch');

        return ['at' => $h ? Carbon::parse($h['at']) : null, 'reason' => $h['reason'] ?? null, 'sent' => (int) ($h['sent'] ?? 0)];
    }

    private function runDispatch(?int $limit): array
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
            $budget = min($limit ?? $this->settings->perRun(), max(0, $dailyLeft));
            if ($budget <= 0) {
                $out['reason'] = 'daily_limit';

                return $out;
            }

            // Jeda antar pesan > 1 menit: tunggu sampai jeda sejak percobaan terakhir terpenuhi (granularitas cron 1 menit).
            $gap = $this->settings->get('gap_seconds');
            $last = $this->lastAttemptAt();
            if ($limit === null && $gap > 60 && $last && $last->addSeconds($gap - 30)->isFuture()) {
                $out['reason'] = 'gap_wait';

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
                if ($this->pauseIfTooManyStops($campaign)) {
                    continue;
                }
                while ($budget > 0) {
                    $message = $this->claimNext($campaign);
                    if (! $message) {
                        break;
                    }
                    $result = $this->sendOne($campaign, $message);
                    $out[$result]++;
                    if ($result === 'sent' || $result === 'uncertain' || $result === 'failed') {
                        $budget--;
                        Cache::put('wa-campaign:last-attempt-at', now()->toIso8601String(), 86400);
                        // Jeda antar pesan supaya tidak menyembur: gap_seconds ±1/3 secara acak
                        // (mis. 30 dtk → 20–40 dtk), jadi ritmenya tidak kaku seperti mesin.
                        if ($budget > 0 && ! app()->runningUnitTests()) {
                            usleep(random_int((int) ($gap * 666_667), (int) ($gap * 1_333_333)));
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

    /** Waktu percobaan kirim terakhir (semua campaign): cache, cadangan dari tabel pesan. */
    private function lastAttemptAt(): ?Carbon
    {
        $cached = Cache::get('wa-campaign:last-attempt-at');
        if ($cached) {
            return Carbon::parse($cached);
        }
        $db = WaCampaignMessage::query()->max('sent_at');

        return $db ? Carbon::parse($db) : null;
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
            $contact->isOptedOut() => 'Berhenti promo (STOP)',
            $campaign->isPermission() && $contact->isOptedIn() => 'Sudah izin promo',
            $campaign->isPermission() && $contact->permission_asked_at !== null => 'Sudah pernah dimintai izin',
            ! $campaign->isPermission() && ! $contact->isOptedIn() => 'Belum ada izin promo',
            $gap > 0 && $contact->last_promo_at && $contact->last_promo_at->gt(now()->subDays($gap)) => "Sudah menerima promo < {$gap} hari",
            default => null,
        };
        if ($skip) {
            $message->forceFill(['status' => WaCampaignMessage::SKIPPED, 'error' => $skip])->save();

            return 'skipped';
        }

        // Redaksi digilir merata antar penerima (versi tetap sama saat dikirim ulang).
        $variant = $message->variant ?? ($message->id % max(1, count($campaign->variants())));
        $text = $this->render($campaign, $contact, $variant);
        $image = $this->imageUrl($campaign);
        $message->forceFill(['rendered_message' => $text, 'variant' => $variant, 'attempts' => $message->attempts + 1])->save();

        $result = $image ? $this->client->sendImage($contact->phone, $image, $text) : $this->client->sendText($contact->phone, $text);

        if ($result['ok']) {
            $message->forceFill(['status' => WaCampaignMessage::ACCEPTED, 'wablas_id' => $result['id'], 'sent_at' => now(), 'error' => null, 'next_attempt_at' => null])->save();
            $contact->forceFill(['last_promo_at' => now()] + ($campaign->isPermission() ? ['permission_asked_at' => now()] : []))->save();
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
    /**
     * Rem darurat reputasi nomor: bila yang membalas STOP melebihi N% dari pesan terkirim
     * (minimal 30 terkirim), campaign dijeda — banyak STOP biasanya diikuti laporan spam.
     * Pesan minta izin memakai batas lebih longgar (STOP memang salah satu jawaban yang
     * ditawarkan). Setelah admin menekan Lanjutkan, hitungan dimulai ulang dari saat itu.
     */
    public function pauseIfTooManyStops(WaCampaign $campaign): bool
    {
        $limit = $this->settings->get($campaign->isPermission() ? 'stop_pause_percent_permission' : 'stop_pause_percent');
        $window = $campaign->messages()->whereNotNull('sent_at')
            ->when($campaign->stop_window_from, fn ($q, $from) => $q->where('sent_at', '>=', $from));
        $sent = (clone $window)->count();
        if ($limit <= 0 || $sent < WaCampaignSettings::STOP_RATE_MIN_SENT) {
            return false;
        }
        $stops = (clone $window)
            ->whereHas('contact', fn ($q) => $q->where('consent_status', WaContact::CONSENT_OUT)->whereColumn('wa_contacts.opted_out_at', '>=', 'wa_campaign_messages.sent_at'))
            ->count();
        $rate = $stops / $sent * 100;
        if ($rate <= $limit) {
            return false;
        }
        $this->pause($campaign, sprintf('%d dari %d penerima membalas STOP (%.1f%% > %d%%) — evaluasi isi pesan & daftar penerima. Tekan Lanjutkan bila tetap ingin mengirim (hitungan STOP dimulai ulang).', $stops, $sent, $rate, $limit), 'error');

        return true;
    }

    /**
     * Balasan "YA" atas pesan minta izin (≤ 30 hari): kontak jadi penerima promo dan
     * langsung dikirimi pesan lanjutan + foto/link produk campaign itu. True bila ditangani.
     */
    public function handlePermissionReply(string $phone, string $text): bool
    {
        if (! app(WaContactService::class)->isYesMessage($text)) {
            return false;
        }
        $contact = WaContact::where('phone', $phone)->first();
        if (! $contact || $contact->isOptedIn() || $contact->isOptedOut() || ! $contact->permission_asked_at || $contact->permission_asked_at->lt(now()->subDays(30))) {
            return false;
        }
        $asked = WaCampaignMessage::where('contact_id', $contact->id)->whereNotNull('sent_at')->latest('sent_at')->get()
            ->first(fn ($m) => $m->campaign?->isPermission());
        $campaign = $asked?->campaign;

        app(WaContactService::class)->optIn($contact, 'wa-reply', 'Membalas "'.Str::limit(trim($text), 40).'" atas pesan minta izin'.($campaign ? ' (campaign "'.$campaign->name.'")' : '').' pada '.now()->format('d/m/Y H:i'));
        if ($campaign) {
            WaCampaignLog::write($campaign->id, "{$phone} membalas YA → izin promo tercatat, pesan lanjutan dikirim.");
        }
        if (! $campaign || blank($campaign->followup_message)) {
            return true;
        }

        $followup = $campaign->replicate();
        $followup->message = (string) $campaign->followup_message;
        $followup->segment = ['kind' => 'promo'];
        $followup->setRelation('product', $campaign->product);
        $body = $this->render($followup, $contact);
        $image = $this->imageUrl($followup);
        $image ? $this->client->sendImage($phone, $image, $body) : $this->client->sendText($phone, $body);
        $contact->forceFill(['last_promo_at' => now()])->save();

        return true;
    }

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
