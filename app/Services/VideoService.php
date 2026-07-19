<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Optional short-video optimisation via ffmpeg. When ffmpeg is installed the
 * uploaded clip is re-encoded small & light (scaled to <=720p, H.264 CRF 28,
 * faststart for instant playback). When ffmpeg is absent the original file is
 * kept as-is (still capped at 20 MB by validation) so the feature still works.
 */
class VideoService
{
    private ?bool $ffmpeg = null;

    public function isFfmpegAvailable(): bool
    {
        if ($this->ffmpeg !== null) {
            return $this->ffmpeg;
        }

        try {
            $p = new Process(['ffmpeg', '-version']);
            $p->setTimeout(10);
            $p->run();
            $this->ffmpeg = $p->isSuccessful();
        } catch (\Throwable $e) {
            $this->ffmpeg = false;
        }

        return $this->ffmpeg;
    }

    /**
     * Compress $absInput to a light MP4 at $absOutput. Returns true on success.
     * No-op (false) when ffmpeg is unavailable.
     */
    public function compress(string $absInput, string $absOutput): bool
    {
        if (! $this->isFfmpegAvailable()) {
            return false;
        }

        // Scale down to max 720px on the long side (keep aspect, even dims),
        // CRF 28 (small but not too blocky), fast start for streaming.
        $process = new Process([
            'ffmpeg', '-y', '-i', $absInput,
            '-vf', "scale='if(gt(iw,ih),min(1280,iw),-2)':'if(gt(iw,ih),-2,min(1280,ih))'",
            '-c:v', 'libx264', '-crf', '28', '-preset', 'veryfast', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart',
            $absOutput,
        ]);
        $process->setTimeout(180);

        try {
            $process->run();
            if ($process->isSuccessful() && is_file($absOutput) && filesize($absOutput) > 0) {
                return true;
            }
            Log::warning('Video compress failed', ['err' => $process->getErrorOutput()]);
        } catch (\Throwable $e) {
            Log::warning('Video compress error: '.$e->getMessage());
        }

        return false;
    }

    /**
     * Extract a poster frame (~0.5s in) to $absOutput (jpg). Returns true on success.
     * No-op when ffmpeg is unavailable.
     */
    public function extractPoster(string $absInput, string $absOutput): bool
    {
        if (! $this->isFfmpegAvailable()) {
            return false;
        }

        $process = new Process(['ffmpeg', '-y', '-ss', '0.5', '-i', $absInput, '-frames:v', '1', $absOutput]);
        $process->setTimeout(60);

        try {
            $process->run();

            return $process->isSuccessful() && is_file($absOutput) && filesize($absOutput) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
