<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


/**
 * CS Assistant powered by Claude (Anthropic). Answers customer questions in
 * Bahasa Indonesia, grounded in our own product catalogue — relevant products
 * are retrieved from the database and injected as context so prices, stock and
 * specs are always accurate (never invented). The API key comes only from config
 * (env); failures are logged and turned into a friendly fallback so a chat outage
 * never throws. No live web access — general renewable-energy knowledge only.
 */
class AssistantService
{
    /** Diagnostics from the last ask(): ['ok'=>bool,'status'=>?int,'body'=>string]. */
    public ?array $lastResult = null;

    /** Words that carry no product-retrieval signal (kept short & pragmatic). */
    private const STOPWORDS = [
        'ada', 'apa', 'apakah', 'yang', 'untuk', 'dengan', 'dan', 'atau', 'ini', 'itu',
        'saya', 'kamu', 'mau', 'ingin', 'bisa', 'gimana', 'bagaimana', 'berapa', 'harga',
        'produk', 'barang', 'tolong', 'mohon', 'min', 'kak', 'bro', 'gan', 'nya', 'aja',
        'dong', 'ya', 'kah', 'the', 'and', 'for', 'with', 'what', 'how', 'much', 'punya',
        'cari', 'jual', 'beli', 'stok', 'ready', 'tersedia', 'butuh', 'perlu', 'rekomendasi',
    ];

    public function __construct(
        private readonly SettingService $settings,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.anthropic.enabled') && filled(config('services.anthropic.api_key'));
    }

    /**
     * Answer a customer question. $history is prior turns as
     * [['role'=>'user'|'assistant','content'=>string], ...]. Returns
     * ['ok'=>bool,'reply'=>string,'products'=>array,'error'=>?string].
     */
    public function ask(string $question, array $history = []): array
    {
        $this->lastResult = null;
        $question = trim($question);

        if ($question === '') {
            return $this->fallback('Silakan tulis pertanyaanmu ya 🙂');
        }

        // Retrieve catalogue context up front (deterministic, from our DB).
        $products = $this->relevantProducts($question);

        if (! $this->isEnabled()) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => 'Anthropic belum aktif (env).'];

            return $this->fallback($this->offlineReply(), $products);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
            ])
                ->asJson()
                ->timeout(45)
                ->post($this->endpoint('/v1/messages'), [
                    'model' => (string) config('services.anthropic.model', 'claude-sonnet-5'),
                    'max_tokens' => 1024,
                    'thinking' => ['type' => 'disabled'], // snappy, low-cost CS replies
                    'system' => $this->systemPrompt($products),
                    'messages' => $this->buildMessages($question, $history),
                ]);

            $this->lastResult = ['ok' => false, 'status' => $response->status(), 'body' => $response->body()];

            if ($response->successful()) {
                $stop = $response->json('stop_reason');
                if ($stop === 'refusal') {
                    return $this->fallback('Maaf, pertanyaan itu di luar yang bisa aku bantu. '.$this->contactLine(), $products);
                }

                $reply = $this->extractText($response->json('content', []));
                if ($reply !== '') {
                    $this->lastResult['ok'] = true;

                    return ['ok' => true, 'reply' => $reply, 'products' => $products, 'error' => null];
                }
            }

            Log::warning('Anthropic chat failed', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
        } catch (\Throwable $e) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
            Log::warning('Anthropic chat error: '.$e->getMessage());
        }

        return $this->fallback('Maaf, asisten lagi sibuk. Coba lagi sebentar ya, atau '.lcfirst($this->contactLine()), $products);
    }

    /** Pull the plain text out of Claude's content-block array. */
    private function extractText(array $content): string
    {
        $text = '';
        foreach ($content as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'] ?? '';
            }
        }

        return trim($text);
    }

    /** Build the messages array: sanitised history + the current question. */
    private function buildMessages(string $question, array $history): array
    {
        $messages = [];
        // Keep only the last few turns to bound cost/latency.
        foreach (array_slice($history, -8) as $turn) {
            $role = $turn['role'] ?? null;
            $content = trim((string) ($turn['content'] ?? ''));
            if (in_array($role, ['user', 'assistant'], true) && $content !== '') {
                $messages[] = ['role' => $role, 'content' => Str::limit($content, 2000, '')];
            }
        }

        // The API requires the first message to be from the user and roles to
        // read sensibly; drop a leading assistant turn if history is malformed.
        while (! empty($messages) && $messages[0]['role'] !== 'user') {
            array_shift($messages);
        }

        $messages[] = ['role' => 'user', 'content' => Str::limit($question, 2000, '')];

        return $messages;
    }

    /**
     * Retrieve up to 6 published products relevant to the question, as compact
     * cards the widget can render and the model can cite. Tokenises the question
     * (a full natural-language sentence won't match a single LIKE/FULLTEXT phrase)
     * and matches any significant word across name/description/keywords/brand.
     */
    private function relevantProducts(string $question): array
    {
        $tokens = $this->keywords($question);
        if (empty($tokens)) {
            return [];
        }

        try {
            $results = Product::published()
                ->with(['brand', 'category'])
                ->where(function ($q) use ($tokens) {
                    foreach ($tokens as $token) {
                        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%';
                        $q->orWhere('name', 'like', $like)
                            ->orWhere('sku', 'like', $like)
                            ->orWhere('model', 'like', $like)
                            ->orWhere('short_description', 'like', $like)
                            ->orWhere('keywords', 'like', $like)
                            ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like));
                    }
                })
                ->orderByDesc('is_featured')
                ->orderByDesc('sold_count')
                ->limit(6)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $results
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'url' => route('products.show', $p->slug),
                'price' => rupiah($p->effectivePrice()),
                'original_price' => $p->isOnSale() ? rupiah((float) $p->price) : null,
                'brand' => $p->brand?->name,
                'category' => $p->category?->name,
                'image' => $p->primaryImageUrl(),
                'in_stock' => $p->inStock(),
                'summary' => $this->plain($p->short_description ?: $p->description, 220),
                'specs' => $this->plain($p->specifications, 320),
            ])
            ->all();
    }

    /** The system prompt: persona, guardrails, store info and product context. */
    private function systemPrompt(array $products): string
    {
        $company = $this->settings->company();
        $brand = $company['brand_name'] ?? brand();
        $wa = $this->settings->whatsappNumber();

        $catalog = $this->catalogBlock($products);

        return <<<PROMPT
Kamu adalah "CS {$brand}" — asisten customer service toko online energi terbarukan {$brand} (by {$company['legal_name']}). Toko menjual panel surya, inverter, baterai, paket PLTS, dan power station portable.

GAYA:
- Jawab dalam Bahasa Indonesia yang ramah, sopan, dan ringkas (to the point). Boleh pakai sedikit emoji secukupnya.
- Sebut harga dalam format Rupiah (mis. "Rp 6.700.000"). Jangan mengarang harga, stok, atau spesifikasi.

ATURAN PENTING:
- Untuk info produk (harga, stok, spesifikasi, ketersediaan), HANYA gunakan data dari "KATALOG TERKAIT" di bawah. Jika produk yang ditanya tidak ada di katalog, katakan kamu belum menemukannya dan sarankan cari di halaman katalog atau tanyakan lebih spesifik — jangan menebak.
- Untuk pertanyaan umum seputar solar/PLTS/energi (cara kerja, tips memilih, estimasi kebutuhan daya), kamu boleh menjawab dengan pengetahuan umum, tapi tetap netral dan jujur bila tidak yakin.
- Jika pelanggan mau memesan, komplain, minta penawaran khusus/instalasi, atau butuh bantuan manusia, arahkan dengan sopan ke WhatsApp {$wa}. Untuk pembelian langsung, arahkan menambahkan produk ke keranjang di situs.
- Jangan pernah meminta atau memproses data sensitif (password, nomor kartu, OTP).
- Kamu tidak punya akses internet; jangan mengklaim mencari di web.

KATALOG TERKAIT (produk dari database toko yang paling relevan dengan pertanyaan):
{$catalog}
PROMPT;
    }

    private function catalogBlock(array $products): string
    {
        if (empty($products)) {
            return '(Tidak ada produk yang cocok dengan kata kunci ini. Bantu pelanggan dengan pengetahuan umum atau minta kata kunci yang lebih spesifik.)';
        }

        $lines = [];
        foreach ($products as $p) {
            $stock = $p['in_stock'] ? 'tersedia' : 'stok habis';
            $meta = trim(implode(' · ', array_filter([$p['brand'], $p['category']])));
            $lines[] = "- {$p['name']}".($meta ? " ({$meta})" : '').": {$p['price']}"
                .($p['original_price'] ? " (dari {$p['original_price']})" : '')
                .", {$stock}. Link: {$p['url']}"
                .($p['summary'] ? "\n  Ringkasan: {$p['summary']}" : '')
                .($p['specs'] ? "\n  Spesifikasi: {$p['specs']}" : '');
        }

        return implode("\n", $lines);
    }

    private function offlineReply(): string
    {
        return 'Halo! 👋 Asisten otomatis lagi belum aktif. Untuk info produk & pemesanan, silakan '.lcfirst($this->contactLine());
    }

    private function contactLine(): string
    {
        $wa = $this->settings->whatsappNumber();

        return $wa ? "hubungi CS kami di WhatsApp {$wa} ya." : 'hubungi CS kami ya.';
    }

    private function fallback(string $reply, array $products = []): array
    {
        return ['ok' => false, 'reply' => $reply, 'products' => $products, 'error' => $this->lastResult['body'] ?? null];
    }

    /** Significant search tokens from a free-text question (max 6, deduped). */
    private function keywords(string $question): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true)) {
                $tokens[$word] = true;
            }
        }

        return array_slice(array_keys($tokens), 0, 6);
    }

    /** Strip HTML/whitespace and truncate for compact prompt/card use. */
    private function plain(?string $html, int $limit): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $html)));

        return $text === '' ? '' : Str::limit($text, $limit);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.anthropic.base_url'), '/').$path;
    }
}
