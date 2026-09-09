<?php

namespace App\Services;

use App\Models\AssistantConversation;
use App\Models\AssistantDailyStat;
use App\Models\AssistantDailyTerm;
use App\Models\AssistantLead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records CS-assistant activity in two layers:
 *  - a raw transcript row (short retention), and
 *  - non-personal daily rollups (volume + top keywords/products, long retention).
 * All writes are wrapped so logging never breaks a chat reply. Gated by
 * config('services.anthropic.logging').
 */
class AssistantAnalytics
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function enabled(): bool
    {
        return (bool) config('services.anthropic.logging', true);
    }

    /**
     * @param  array  $result  the AssistantService::ask() result
     *                         (['ok','reply','products',...])
     */
    public function record(string $question, array $result, ?string $sessionId, ?string $ip, ?string $imagePath = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            $sessionId = $sessionId ? Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $sessionId), 64, '') : null;
            $answered = (bool) ($result['ok'] ?? false);
            $products = $result['products'] ?? [];
            $day = now()->toDateString();

            // New unique session for the day? Check before inserting this row.
            $newSession = $sessionId
                && ! AssistantConversation::where('session_id', $sessionId)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->exists();

            AssistantConversation::create([
                'session_id' => $sessionId,
                'message' => Str::limit($question, 2000),
                'image_path' => $imagePath,
                'reply' => Str::limit((string) ($result['reply'] ?? ''), 4000),
                'answered' => $answered,
                'product_slugs' => collect($products)->pluck('slug')->filter()->values()->all(),
                'model' => $answered ? (string) config('services.anthropic.model') : null,
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
                'created_at' => now(),
            ]);

            $stat = $this->dailyStat($day);
            $stat->increment('messages');
            $stat->increment($answered ? 'answered' : 'fallbacks');
            if ($newSession) {
                $stat->increment('sessions');
            }

            // Keyword rollup (what customers ask about).
            foreach ($this->assistant->keywords($question) as $keyword) {
                $this->bumpTerm($day, 'keyword', $keyword);
            }

            // Product rollup (what got recommended).
            foreach ($products as $p) {
                if (! empty($p['slug'])) {
                    $this->bumpTerm($day, 'product', $p['slug'], $p['name'] ?? null);
                }
            }

            // Knowledge gap: kebutuhan yang tidak terlayani katalog (produk yang
            // dicari tapi belum ada) — bahan perencanaan penambahan produk.
            foreach (($result['gaps'] ?? []) as $gap) {
                $this->bumpTerm($day, 'gap', mb_strtolower($gap), $gap);
            }
        } catch (\Throwable $e) {
            Log::warning('Assistant analytics failed: '.$e->getMessage());
        }
    }

    /**
     * Upsert a lead (name/phone the customer shared in chat) for a session.
     * Independent of the logging flag — capturing contacts is the point.
     * Only fills/overwrites fields that actually arrived.
     */
    public function captureLead(?array $lead, ?string $sessionId): void
    {
        if (! $lead || ! $sessionId) {
            return;
        }

        try {
            $sessionId = Str::limit(preg_replace('/[^A-Za-z0-9_-]/', '', $sessionId), 64, '');
            if ($sessionId === '') {
                return;
            }

            $row = AssistantLead::firstOrNew(['session_id' => $sessionId]);
            if (! empty($lead['name'])) {
                $row->name = $lead['name'];
            }
            if (! empty($lead['phone'])) {
                $row->phone = $lead['phone'];
            }
            if (! empty($lead['need'])) {
                $row->need = Str::limit($lead['need'], 500, '');
            }
            $row->save();
        } catch (\Throwable $e) {
            Log::warning('Assistant lead capture failed: '.$e->getMessage());
        }
    }

    /**
     * Funnel click from the chat UI: a product card or the WhatsApp hand-off
     * button was tapped. Rolled up per day (+ per product for top-clicked).
     */
    public function recordClick(string $type, ?string $slug, ?string $label = null): void
    {
        if (! $this->enabled() || ! in_array($type, ['product', 'whatsapp'], true)) {
            return;
        }

        try {
            $this->dailyStat(now()->toDateString())
                ->increment($type === 'product' ? 'product_clicks' : 'wa_clicks');

            if ($type === 'product' && $slug) {
                $this->bumpTerm(now()->toDateString(), 'click', Str::limit($slug, 191, ''), $label);
            }
        } catch (\Throwable $e) {
            Log::warning('Assistant click tracking failed: '.$e->getMessage());
        }
    }

    /**
     * Baris rollup hari ini. Lookup pakai whereDate, BUKAN firstOrCreate biasa —
     * cast `date` menyimpan nilai dengan komponen jam di beberapa driver,
     * sehingga where('day', '2026-09-09') tidak menemukan baris yang ada dan
     * insert kedua meledak di unique constraint (increment-nya hilang).
     */
    private function dailyStat(string $day): AssistantDailyStat
    {
        return AssistantDailyStat::whereDate('day', $day)->first()
            ?? AssistantDailyStat::create(['day' => $day]);
    }

    private function bumpTerm(string $day, string $type, string $term, ?string $label = null): void
    {
        $term = Str::limit($term, 191, '');
        $row = AssistantDailyTerm::whereDate('day', $day)->where('type', $type)->where('term', $term)->first()
            ?? AssistantDailyTerm::create([
                'day' => $day, 'type' => $type, 'term' => $term,
                'label' => $label ? Str::limit($label, 255, '') : null,
            ]);
        $row->increment('count');
    }
}
