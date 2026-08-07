<?php

namespace App\Console\Commands;

use App\Models\WaMessage;
use App\Support\WablasMedia;
use Illuminate\Console\Command;

/**
 * Converts historical rows where an attachment was flattened into the message
 * text ("[media] abc.jpeg") into proper media columns, so old conversations
 * render thumbnails/links like new ones. Safe to re-run.
 */
class BackfillWaMedia extends Command
{
    protected $signature = 'wa:backfill-media {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Ubah pesan WA lama berformat "[media] namafile" atau URL polos menjadi lampiran yang bisa ditampilkan';

    public function handle(): int
    {
        $rows = WaMessage::whereNull('media_url')
            ->where(fn ($q) => $q->where('message', 'like', '[media]%')->orWhere('message', 'like', 'http%'))
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada pesan lama yang perlu dikonversi.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');
        $converted = 0;

        foreach ($rows as $row) {
            $text = (string) $row->message;
            // Two legacy shapes: "[media] namafile" and a bare media URL that
            // the webhook used to record as ordinary text.
            $file = str_starts_with(mb_strtolower(ltrim($text)), '[media]')
                ? trim(preg_replace('/^\s*\[media\]\s*/i', '', $text))
                : (string) WablasMedia::fromText($text);

            if ($file === '') {
                continue;
            }

            $url = WablasMedia::url($file);
            if (! $url) {
                continue;
            }

            $this->line(sprintf('%s → %s (%s)', $row->phone, WablasMedia::name($file), WablasMedia::type('', $file)));

            if (! $dry) {
                $row->forceFill([
                    'message' => '',                     // caption was never there
                    'media_url' => $url,
                    'media_type' => WablasMedia::type('', $file),
                    'media_name' => WablasMedia::name($file),
                ])->save();
            }

            $converted++;
        }

        $base = trim((string) config('services.wablas.media_base_url'));
        $this->info(($dry ? '[dry-run] ' : '').$converted.' pesan dikonversi.');
        if ($base === '') {
            $this->warn('WABLAS_MEDIA_BASE_URL belum diisi — lampiran masih berupa nama berkas, belum bisa dibuka. Isi di .env lalu jalankan ulang perintah ini.');
        }

        return self::SUCCESS;
    }
}
