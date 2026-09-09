<?php

namespace App\Http\Controllers;

use App\Models\AssistantConversation;
use App\Models\AssistantLead;
use App\Models\Product;
use App\Services\AssistantAnalytics;
use App\Services\AssistantService;
use App\Services\SettingService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            'message' => ['required_without:image', 'nullable', 'string', 'max:1000'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:#^chat-uploads/[A-Za-z0-9/_.\-]+$#'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'product_slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'history' => ['sometimes', 'array', 'max:30'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        // Foto dari composer: hanya path hasil endpoint unggah yang diterima.
        $imagePath = ($data['image'] ?? null) && Storage::disk('public')->exists($data['image'])
            ? $data['image']
            : null;

        // "Tanya Produk Ini": pin the product page the chat was opened from.
        $focus = ! empty($data['product_slug'])
            ? Product::published()->with(['brand', 'category'])->where('slug', $data['product_slug'])->first()
            : null;

        $message = (string) ($data['message'] ?? '');
        $result = $assistant->ask($message, $data['history'] ?? [], $focus, $imagePath);

        $analytics->captureLead($result['lead'] ?? null, $data['session_id'] ?? null);
        $analytics->record($message !== '' ? $message : '📷 (foto)', $result, $data['session_id'] ?? null, $request->ip(), $imagePath);

        // The CS WhatsApp number is only revealed after the customer leaves
        // name + WA number + what they need (the widget shows a small form).
        // A session whose lead is already complete skips straight to the button.
        $escalate = (bool) ($result['escalate'] ?? false);
        $whatsapp = $result['whatsapp'] ?? null;
        $leadForm = false;
        if ($escalate && $whatsapp) {
            $lead = $this->sessionLead($data['session_id'] ?? null);
            if (! ($lead && filled($lead->name) && filled($lead->phone))) {
                $whatsapp = null;
                $leadForm = true;
            }
        }

        return response()->json([
            'reply' => $result['reply'],
            'products' => $result['products'],
            'escalate' => $escalate,
            'whatsapp' => $whatsapp,
            'lead_form' => $leadForm,
        ]);
    }

    /**
     * Unggah foto dari composer chat (nameplate, atap, meteran, dsb.). File
     * disimpan dulu, lalu path-nya dikirim bersama pesan berikutnya supaya
     * request chat tetap ringan dan bisa di-retry tanpa unggah ulang.
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['image']->store('chat-uploads/'.now()->format('Y/m'), 'public');

        return response()->json(['ok' => true, 'path' => $path, 'url' => asset('storage/'.$path)]);
    }

    /**
     * Funnel tracking: a product card / WhatsApp button inside the chat was
     * clicked. Fire-and-forget from the widget; must never break the UI.
     */
    public function click(Request $request, AssistantAnalytics $analytics): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:product,whatsapp'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $slug = $data['slug'] ?? null;
        $analytics->recordClick(
            $data['type'],
            $slug,
            $slug ? Product::where('slug', $slug)->value('name') : null,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Pre-WhatsApp contact form: the customer must leave their name, WA number
     * and what they need; only then is the CS WhatsApp link handed out. The
     * data lands in the same AssistantLead row the chat's [[DATA]] token feeds.
     */
    public function contact(Request $request, AssistantAnalytics $analytics, WhatsAppService $wa, SettingService $settings): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'need' => ['required', 'string', 'max:500'],
        ]);

        $phone = $wa->normalize($data['phone']);
        if (! $phone) {
            return response()->json(['ok' => false, 'error' => 'Nomor WhatsApp tidak valid — cek lagi ya Kak (contoh: 0812xxxxxxx).'], 422);
        }

        $analytics->captureLead([
            'name' => trim($data['name']),
            'phone' => $phone,
            'need' => trim($data['need']),
        ], $data['session_id']);

        $whatsapp = $settings->whatsappNumber()
            ? whatsapp_link('Halo CS '.brand().', saya '.trim($data['name']).'. Kebutuhan saya: '.Str::limit(trim($data['need']), 300).' 🙏')
            : null;

        return response()->json(['ok' => true, 'whatsapp' => $whatsapp]);
    }

    /** The stored lead for a chat session id, if any. */
    private function sessionLead(?string $sessionId): ?AssistantLead
    {
        $sessionId = $sessionId ? preg_replace('/[^A-Za-z0-9_-]/', '', $sessionId) : '';
        if ($sessionId === '') {
            return null;
        }

        try {
            return AssistantLead::where('session_id', $sessionId)->first();
        } catch (\Throwable $e) {
            return null;
        }
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
            $messages[] = [
                'role' => 'user',
                'content' => $t->message,
                'image' => $t->image_path ? asset('storage/'.$t->image_path) : null,
                'products' => [],
            ];
            $cards = collect($t->product_slugs ?? [])
                ->map(fn ($slug) => $products[$slug] ?? null)
                ->filter()
                ->take(6)
                ->map(fn (Product $p) => [
                    'name' => $p->name,
                    'slug' => $p->slug,
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
