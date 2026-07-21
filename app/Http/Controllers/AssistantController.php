<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\Product;
use App\Services\AssistantAnalytics;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront CS chat assistant endpoint. Grounds answers in the product
 * catalogue via AssistantService (Claude). Rate-limited in the route; each
 * exchange is logged (transcript + rollups) via AssistantAnalytics.
 */
class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant, AssistantAnalytics $analytics): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'history' => ['sometimes', 'array', 'max:30'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $result = $assistant->ask($data['message'], $data['history'] ?? []);

        $analytics->captureLead($result['lead'] ?? null, $data['session_id'] ?? null);
        $analytics->record($data['message'], $result, $data['session_id'] ?? null, $request->ip());

        return response()->json([
            'reply' => $result['reply'],
            'products' => $result['products'],
            'escalate' => $result['escalate'] ?? false,
            'whatsapp' => $result['whatsapp'] ?? null,
        ]);
    }

    /**
     * Restore a session's recent chat history (so a page refresh doesn't wipe
     * the conversation). Keyed by the per-browser session id — NOT by IP, which
     * is shared on office/mobile networks and would leak other people's chats.
     * Product cards are rebuilt from the stored slugs.
     */
    public function history(Request $request): JsonResponse
    {
        $data = $request->validate(['session_id' => ['required', 'string', 'max:64']]);
        $sessionId = preg_replace('/[^A-Za-z0-9_-]/', '', $data['session_id']);

        if ($sessionId === '' || ! config('services.anthropic.logging', true)) {
            return response()->json(['messages' => []]);
        }

        // Last 15 exchanges, oldest first (transcripts are pruned after ~30 days).
        $turns = AssistantConversation::where('session_id', $sessionId)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->reverse()
            ->values();

        $slugs = $turns->flatMap(fn ($t) => $t->product_slugs ?? [])->unique()->values();
        $products = $slugs->isEmpty()
            ? collect()
            : Product::published()->whereIn('slug', $slugs)->with('brand')->get()->keyBy('slug');

        $messages = [];
        foreach ($turns as $t) {
            $messages[] = ['role' => 'user', 'content' => $t->message, 'products' => []];
            $cards = collect($t->product_slugs ?? [])
                ->map(fn ($slug) => $products[$slug] ?? null)
                ->filter()
                ->take(6)
                ->map(fn (Product $p) => [
                    'name' => $p->name,
                    'url' => route('products.show', $p->slug),
                    'price' => rupiah($p->effectivePrice()),
                    'image' => $p->primaryImageUrl(),
                    'in_stock' => $p->inStock(),
                ])
                ->values()
                ->all();
            $messages[] = ['role' => 'assistant', 'content' => $t->reply, 'products' => $cards];
        }

        return response()->json(['messages' => $messages]);
    }
}
