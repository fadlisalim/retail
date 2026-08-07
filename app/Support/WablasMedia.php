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
    /** Extension => media type. Anything unlisted is treated as a document. */
    private const TYPES = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'],
        'video' => ['mp4', 'mkv', '3gp', 'mov', 'f4v', 'flv', 'webm'],
        'audio' => ['mp3', 'ogg', 'opus', 'wav', 'm4a', 'aac'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip', 'rar'],
    ];

    /** Every extension we are willing to recognise inside a bare-URL message. */
    private const EXTENSIONS = [
        ...self::TYPES['image'], ...self::TYPES['video'],
        ...self::TYPES['audio'], ...self::TYPES['document'],
    ];

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

        foreach (self::TYPES as $type => $extensions) {
            if (in_array($ext, $extensions, true)) {
                return $type;
            }
        }

        return 'document';
    }

    /**
     * Some Wablas setups deliver an attachment with no `file` field at all —
     * the media URL simply arrives as the message body
     * ("https://pati.wablas.com/image/SKJB-….jpeg"). Detect that so the inbox
     * shows a thumbnail instead of a bare link. Only a message that is exactly
     * one URL counts; a real sentence that happens to contain a link is left
     * as text.
     */
    public static function fromText(string $message): ?string
    {
        $text = trim($message);

        if ($text === '' || preg_match('/\s/', $text) || ! Str::startsWith($text, ['http://', 'https://'])) {
            return null;
        }

        $ext = mb_strtolower(pathinfo((string) parse_url($text, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONS, true) ? $text : null;
    }

    /** Filename shown as the label for non-image attachments. */
    public static function name(string $file): string
    {
        return Str::limit(basename(parse_url($file, PHP_URL_PATH) ?: $file), 191, '');
    }
}
