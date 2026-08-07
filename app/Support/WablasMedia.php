<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns whatever Wablas reports about an attachment into a usable URL and a
 * media type. Some servers post a full URL, others only the stored filename —
 * the latter needs services.wablas.media_base_url to become fetchable.
 * Shared by the webhook and the backfill command so both behave identically.
 */
class WablasMedia
{
    /** Absolute URL when possible; otherwise the raw filename (never lost). */
    public static function url(string $file): ?string
    {
        $file = trim($file);
        if ($file === '') {
            return null;
        }

        if (Str::startsWith($file, ['http://', 'https://'])) {
            return Str::limit($file, 500, '');
        }

        $base = trim((string) config('services.wablas.media_base_url'));

        return $base === ''
            ? Str::limit($file, 500, '')
            : Str::limit(rtrim($base, '/').'/'.ltrim($file, '/'), 500, '');
    }

    /** Wablas' own message type wins; the file extension is the fallback. */
    public static function type(string $reported, string $file): string
    {
        $reported = mb_strtolower(trim($reported));
        foreach (['image', 'video', 'audio', 'document'] as $type) {
            if ($reported !== '' && str_contains($reported, $type)) {
                return $type;
            }
        }

        $ext = mb_strtolower(pathinfo(parse_url($file, PHP_URL_PATH) ?: $file, PATHINFO_EXTENSION));

        return match (true) {
            in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true) => 'image',
            in_array($ext, ['mp4', 'mkv', '3gp', 'mov', 'f4v', 'flv', 'webm'], true) => 'video',
            in_array($ext, ['mp3', 'ogg', 'opus', 'wav', 'm4a', 'aac'], true) => 'audio',
            default => 'document',
        };
    }

    /** Filename shown as the label for non-image attachments. */
    public static function name(string $file): string
    {
        return Str::limit(basename(parse_url($file, PHP_URL_PATH) ?: $file), 191, '');
    }
}
