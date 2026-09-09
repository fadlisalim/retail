<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Words that carry no product-retrieval signal — dropped before matching so
     * retrieval focuses on real product/brand terms (e.g. "bluetti", "panel
     * surya", "inverter") instead of generic words like "garansi"/"lama" that
     * appear in many product descriptions and pollute the results.
     */
    private const STOPWORDS = [
        // question / filler / pronouns
        'ada', 'apa', 'apakah', 'yang', 'yg', 'untuk', 'dengan', 'dan', 'atau', 'ini', 'itu',
        'saya', 'kamu', 'mau', 'ingin', 'bisa', 'gimana', 'bagaimana', 'berapa', 'mana', 'kenapa',
        'tolong', 'mohon', 'min', 'kak', 'bro', 'gan', 'nya', 'aja', 'dong', 'deh', 'sih', 'kok',
        'ya', 'kah', 'punya', 'the', 'and', 'for', 'with', 'what', 'how', 'much', 'lebih', 'paling',
        // generic commerce / spec words (match too many products via description)
        'produk', 'barang', 'harga', 'cari', 'jual', 'beli', 'stok', 'ready', 'tersedia', 'butuh',
        'perlu', 'rekomendasi', 'garansi', 'lama', 'tahun', 'thn', 'merk', 'merek', 'tipe', 'model',
        'warna', 'ukuran', 'berat', 'spesifikasi', 'spek', 'fitur', 'kelebihan', 'kualitas', 'promo',
        'diskon', 'murah', 'mahal', 'bagus', 'cocok', 'kirim', 'ongkir', 'bayar', 'info', 'detail',
        'tanya', 'sekitar', 'kira', 'kisaran', 'daya', 'watt',
        // marketing words that appear inside product NAMES and would otherwise
        // qualify a single product for generic questions ("tagihan 2jt" must
        // not retrieve only "…Pangkas Tagihan PLN")
        'tagihan', 'hemat', 'pangkas', 'pln', 'listrik', 'bulan', 'juta',
        'referensi', 'referensikan', 'rekomendasiin', 'rekomendasikan',
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
     * [['role'=>'user'|'assistant','content'=>string], ...]. $focus is the
     * product page the customer opened the chat from ("Tanya Produk Ini") —
     * it is pinned into the context so "produk ini" questions stay grounded.
     * Returns ['ok'=>bool,'reply'=>string,'products'=>array,'escalate'=>bool,
     *  'whatsapp'=>?string,'error'=>?string].
     */
    public function ask(string $question, array $history = [], ?Product $focus = null, ?string $imagePath = null): array
    {
        $this->lastResult = null;
        $question = trim($question);

        if ($question === '' && ! $imagePath) {
            return $this->fallback('Silakan tulis pertanyaanmu ya 🙂');
        }

        // Retrieve catalogue context up front (deterministic, from our DB).
        $products = $this->relevantProducts($question);
        if ($focus) {
            $products = collect([$this->productCard($focus)])
                ->concat($products)
                ->unique('slug')
                ->take(6)
                ->values()
                ->all();
        }

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
                    'max_tokens' => 900, // singkat ala WhatsApp; ruang ekstra untuk ringkasan/rakitan

                    'thinking' => ['type' => 'disabled'], // snappy, low-cost CS replies
                    'system' => $this->systemPrompt($products, $focus?->name),
                    'messages' => $this->buildMessages($question, $history, $imagePath),
                ]);

            $this->lastResult = ['ok' => false, 'status' => $response->status(), 'body' => $response->body()];

            if ($response->successful()) {
                $stop = $response->json('stop_reason');
                if ($stop === 'refusal') {
                    return $this->fallback('Maaf, pertanyaan itu di luar yang bisa aku bantu. Untuk hal ini, silakan hubungi CS kami lewat tombol WhatsApp di bawah ya 🙏', $products);
                }

                $reply = $this->extractText($response->json('content', []));
                if ($reply !== '') {
                    $this->lastResult['ok'] = true;

                    // The model appends the token [[WA]] when it can't answer from
                    // the catalogue or the customer needs a human — we strip it and
                    // surface a WhatsApp button instead of a raw number.
                    $escalate = str_contains($reply, '[[WA]]');
                    $reply = trim(preg_replace('/\s*\[\[WA\]\]\s*/', '', $reply));

                    // Hidden lead token [[DATA nama="..." hp="..."]] — emitted when
                    // the customer shares their name/phone. Stripped before display.
                    [$reply, $lead] = $this->extractLead($reply);

                    // Hidden gap token [[GAP kebutuhan="..."]] — unmet demand the
                    // catalogue can't serve, logged for assortment planning.
                    [$reply, $gaps] = $this->extractGaps($reply);

                    // Hidden card token [[PRODUK nama 1 | nama 2]] — the model
                    // decides WHEN cards appear (link-timing) and which ones. No
                    // token = no cards: an education answer must not be flooded
                    // with retrieval candidates.
                    [$reply, $cards] = $this->extractRecommendations($reply);

                    return $this->result(true, $reply, $cards, $escalate, $lead, $gaps);
                }
            }

            Log::warning('Anthropic chat failed', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
        } catch (\Throwable $e) {
            $this->lastResult = ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
            Log::warning('Anthropic chat error: '.$e->getMessage());
        }

        return $this->fallback('Maaf, asisten lagi sibuk. Coba lagi sebentar ya, atau hubungi CS kami lewat tombol WhatsApp di bawah 🙏', $products);
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

    /**
     * Build the messages array: sanitised history + the current question. A
     * photo the customer attached rides along as an image block on the CURRENT
     * turn only (past photos stay out of history to bound cost).
     */
    private function buildMessages(string $question, array $history, ?string $imagePath = null): array
    {
        $messages = [];
        // Last 12 turns: enough consultation memory (budget, devices, rejected
        // options stay in view) while still bounding cost/latency.
        foreach (array_slice($history, -12) as $turn) {
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

        $text = Str::limit($question, 2000, '');
        $imageBlock = $imagePath ? $this->imageBlock($imagePath) : null;

        if ($imageBlock) {
            $content = [$imageBlock];
            $content[] = ['type' => 'text', 'text' => $text !== '' ? $text : '(Pelanggan mengirim foto tanpa teks — tanggapi fotonya.)'];
            $messages[] = ['role' => 'user', 'content' => $content];
        } else {
            $messages[] = ['role' => 'user', 'content' => $text];
        }

        return $messages;
    }

    /** Base64 image block for a chat upload on the public disk, or null. */
    private function imageBlock(string $path): ?array
    {
        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path) || $disk->size($path) > 4_000_000) {
                return null; // batas aman API ±5MB; file lebih besar dilewati
            }

            $mediaType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => null,
            };
            if (! $mediaType) {
                return null;
            }

            return [
                'type' => 'image',
                'source' => ['type' => 'base64', 'media_type' => $mediaType, 'data' => base64_encode($disk->get($path))],
            ];
        } catch (\Throwable $e) {
            return null;
        }
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
        $results = collect();

        if (! empty($tokens)) {
            try {
                // Qualifier: a token must hit a STRONG field (name/keywords/sku/model/
                // brand). Description is deliberately EXCLUDED here — otherwise a
                // battery/inverter whose description merely mentions "panel surya"
                // would surface for a "panel surya" question. Relevance is then scored
                // so name matches rank above keyword matches, and the top 6 are the
                // genuinely on-topic products.
                $scoreParts = [];
                $scoreBindings = [];
                foreach ($tokens as $token) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%';
                    $scoreParts[] = '(CASE WHEN name LIKE ? THEN 3 ELSE 0 END)'
                        .' + (CASE WHEN keywords LIKE ? THEN 2 ELSE 0 END)'
                        .' + (CASE WHEN COALESCE(short_description, description, \'\') LIKE ? THEN 1 ELSE 0 END)';
                    array_push($scoreBindings, $like, $like, $like);
                }

                $results = Product::published()
                    ->with(['brand', 'category'])
                    ->select('products.*')
                    ->selectRaw('('.implode(' + ', $scoreParts).') as relevance', $scoreBindings)
                    ->where(function ($q) use ($tokens) {
                        foreach ($tokens as $token) {
                            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $token).'%';
                            // Qualify on the product NAME, SKU/model, or brand only.
                            // `keywords` is a broad SEO blob (every product carries
                            // generic terms like "panel surya", "energi surya"), so
                            // matching it here would surface unrelated products — it's
                            // used for scoring instead, never as a qualifier.
                            $q->orWhere('name', 'like', $like)
                                ->orWhere('sku', 'like', $like)
                                ->orWhere('model', 'like', $like)
                                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like));
                        }
                    })
                    ->orderByDesc('relevance')
                    ->orderByDesc('is_featured')
                    ->orderByDesc('sold_count')
                    ->limit(6)
                    ->get();
            } catch (\Throwable $e) {
                $results = collect();
            }
        }

        // Sizing/recommendation questions ("paket buat rumah 4400W tagihan
        // 2jt?") carry no product-name token, so keyword retrieval finds little
        // or nothing (or one accidental hit on marketing copy). Top the results
        // up with the PAKET line-up (cheapest first) so the model can compare
        // real options instead of claiming the catalogue is empty.
        if ($results->count() < 6 && $this->asksForSystemPackage($question)) {
            try {
                $pakets = Product::published()
                    ->with(['brand', 'category'])
                    ->whereNotIn('id', $results->pluck('id'))
                    ->where(function ($q) {
                        $q->where('slug', 'like', 'paket%')
                            ->orWhere('name', 'like', '%paket%')
                            ->orWhere('name', 'like', '%plts%')
                            ->orWhereHas('categories', fn ($c) => $c->where('slug', 'like', 'paket%'));
                    })
                    ->orderBy('price')
                    ->limit(6 - $results->count())
                    ->get();
                $results = $results->concat($pakets);
            } catch (\Throwable $e) {
                // keep whatever we already have
            }
        }

        // "Rakitkan sistem 5000W lengkap panel + inverter + baterai" — pelanggan
        // minta dirangkaikan dari produk satuan. Retrieval kata kunci hanya
        // menemukan produk yang kebetulan memuat kata "panel"/"inverter", belum
        // tentu yang dayanya pas, jadi kandidat komponen dipilih deterministik
        // per kategori (inverter berdaya ≥ permintaan, panel Wp terbesar,
        // baterai terlaris) dan ditaruh paling atas supaya model merakit dari
        // spesifikasi yang benar.
        if ($this->asksForComponentBuild($question)) {
            try {
                $components = $this->componentCandidates($this->requestedWatts($question));
                $results = $components
                    ->concat(
                        // Rakitan = beli langsung dengan total harga, jadi sisa
                        // hasil kata kunci yang khusus-penawaran tidak relevan.
                        $results->whereNotIn('id', $components->pluck('id'))
                            ->where('requires_quotation', false),
                    )
                    ->take(9)
                    ->values();
            } catch (\Throwable $e) {
                // keep whatever we already have
            }
        }

        return $results->map(fn (Product $p) => $this->productCard($p))->all();
    }

    /**
     * Does the customer want a SYSTEM ASSEMBLED from individual components
     * ("rakitkan 5000W lengkap panel, inverter, baterai")? Assembly verbs
     * count, and so does naming two or more component kinds in one breath.
     */
    private function asksForComponentBuild(string $question): bool
    {
        $q = mb_strtolower($question);

        if (preg_match('/\b(rakit\w*|merakit\w*|rangkai\w*|merangkai\w*|susun\w*|konfigurasi\w*|kombinasi\w*|set\s+lengkap|bundling)\b/u', $q)) {
            return true;
        }

        $kinds = 0;
        foreach (['/panel/u', '/inverter/u', '/(baterai|batere|battery|\baki\b)/u'] as $pattern) {
            $kinds += preg_match($pattern, $q) ? 1 : 0;
        }

        return $kinds >= 2;
    }

    /** Requested system power in watts, parsed from "5000W" / "5 kW" / "daya 5000". */
    private function requestedWatts(string $question): ?int
    {
        $q = mb_strtolower($question);

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*k(?:w|va)\b/u', $q, $m)) {
            return (int) round(((float) str_replace(',', '.', $m[1])) * 1000);
        }
        // "5000W" / "5.000 watt" / "5000 VA" — \b setelah w menolak "580Wp".
        if (preg_match('/(\d{1,3}(?:\.\d{3})+|\d{3,6})\s*(?:watt|w|va)\b/u', $q, $m)) {
            return (int) str_replace('.', '', $m[1]);
        }
        if (preg_match('/daya\s+(?:sekitar\s+)?(\d{1,3}(?:\.\d{3})+|\d{3,6})\b/u', $q, $m)) {
            return (int) str_replace('.', '', $m[1]);
        }

        return null;
    }

    /**
     * Deterministic component candidates for a system build: up to 2 inverters
     * sized to the requested watts, 2 highest-Wp panels, and 2 best-selling
     * batteries — all published and directly purchasable.
     *
     * @return Collection<int, Product>
     */
    private function componentCandidates(?int $watts): Collection
    {
        $inverters = $this->categoryPool('inverter');
        $picks = collect();

        if ($watts !== null) {
            // Inverter dengan daya kontinu ≥ permintaan, ambil yang terdekat di
            // atasnya; kalau tidak ada yang cukup besar, dua terbesar (model
            // yang menjelaskan keterbatasannya ke pelanggan).
            $rated = $inverters
                ->map(fn (Product $p) => ['product' => $p, 'watts' => $this->productWatts($p)])
                ->filter(fn (array $r) => $r['watts'] !== null);
            $above = $rated->filter(fn (array $r) => $r['watts'] >= $watts)->sortBy('watts');
            $picks = $picks->concat(
                ($above->isNotEmpty() ? $above : $rated->sortByDesc('watts'))->take(2)->pluck('product'),
            );
        } else {
            $picks = $picks->concat($inverters->sortByDesc('sold_count')->take(2));
        }

        $picks = $picks->concat(
            $this->categoryPool('panel-surya')
                ->sortByDesc(fn (Product $p) => ($p->inStock() ? 1_000_000 : 0) + ($this->panelWp($p) ?? 0))
                ->take(2),
        );

        return $picks
            ->concat(
                $this->categoryPool('baterai')
                    ->sortByDesc(fn (Product $p) => ($p->inStock() ? 1_000_000_000 : 0) + (int) $p->sold_count)
                    ->take(2),
            )
            ->unique('id')
            ->values();
    }

    /** Published, directly-purchasable products under a root category (incl. children). */
    private function categoryPool(string $rootSlug): Collection
    {
        $root = Category::where('slug', $rootSlug)->first();
        if (! $root) {
            return collect();
        }

        $ids = Category::where('id', $root->id)->orWhere('parent_id', $root->id)->pluck('id');

        return Product::published()
            ->with(['brand', 'category'])
            ->where('requires_quotation', false)
            ->where(function ($q) use ($ids) {
                $q->whereIn('category_id', $ids)
                    ->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids));
            })
            ->get()
            ->collect();
    }

    /** Highest continuous power (W) found in a product's name/specs, or null. */
    private function productWatts(Product $p): ?int
    {
        $text = mb_strtolower($p->name.' '.strip_tags((string) $p->specifications));
        $best = null;

        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*k(?:w|va)\b/u', $text, $m)) {
            foreach ($m[1] as $value) {
                $best = max($best ?? 0, (int) round(((float) str_replace(',', '.', $value)) * 1000));
            }
        }
        if (preg_match_all('/(\d{1,3}(?:\.\d{3})+|\d{3,6})\s*(?:watt|w|va)\b/u', $text, $m)) {
            foreach ($m[1] as $value) {
                $best = max($best ?? 0, (int) str_replace('.', '', $value));
            }
        }

        return $best;
    }

    /** Panel rating in Wp from name/specs (e.g. "580Wp"), or null. */
    private function panelWp(Product $p): ?int
    {
        $text = mb_strtolower($p->name.' '.strip_tags((string) $p->specifications));

        return preg_match_all('/(\d{2,4})\s*wp\b/u', $text, $m)
            ? max(array_map('intval', $m[1]))
            : null;
    }

    /** Compact card/context payload for one product. */
    private function productCard(Product $p): array
    {
        return [
            'name' => $p->name,
            'slug' => $p->slug,
            'url' => route('products.show', $p->slug),
            'price' => rupiah($p->effectivePrice()),
            'original_price' => $p->isOnSale() ? rupiah((float) $p->price) : null,
            'brand' => $p->brand?->name,
            'category' => $p->category?->name,
            'image' => $p->primaryImageUrl(),
            'in_stock' => $p->inStock(),
            // Honest persuasion hooks (only real data — the model must not invent these).
            'discount' => $p->isOnSale() ? $p->discountPercent() : null,
            'low_stock' => $p->inStock() && $p->isLowStock(),
            'warranty' => $p->warranty,
            'rating' => (int) $p->rating_count > 0 ? ['avg' => round((float) $p->rating_avg, 1), 'count' => (int) $p->rating_count] : null,
            // Full description (short + long combined) so the model can
            // answer from the same copy customers read on the product page —
            // isi paket, ilustrasi beban, garansi per komponen, dsb.
            'summary' => $this->plain(trim(($p->short_description ?? '').' '.($p->description ?? '')), 900),
            // 1400: parameter listrik (rentang MPPT, Voc/Vmp/Imp, arus input)
            // sering ada di bagian akhir spesifikasi — jangan sampai terpotong,
            // perhitungan string panel bergantung pada angka-angka ini.
            'specs' => $this->plain($p->specifications, 1400),
        ];
    }

    /**
     * Strip the hidden [[PRODUK nama 1 | nama 2]] token and resolve the named
     * products into cards. The model decides WHEN cards appear (link-timing:
     * buying intent / strong recommendation — not greetings or education) and
     * WHICH products, so no token means no cards.
     *
     * @return array{0: string, 1: array}
     */
    private function extractRecommendations(string $reply): array
    {
        $names = [];
        $clean = preg_replace_callback('/\s*\[\[PRODUK([^\]]*)\]\]\s*/iu', function ($m) use (&$names) {
            foreach (explode('|', $m[1]) as $name) {
                $name = trim($name, " \t\n\r:·-");
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return "\n";
        }, $reply);

        if (empty($names)) {
            return [$reply, []];
        }

        $cards = [];
        try {
            foreach (array_slice($names, 0, 6) as $name) {
                $product = Product::published()->with(['brand', 'category'])
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                    ?? Product::published()->with(['brand', 'category'])
                        ->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $name).'%')->first();
                if ($product && ! isset($cards[$product->slug])) {
                    $cards[$product->slug] = $this->productCard($product);
                }
            }
        } catch (\Throwable $e) {
            return [trim($clean), []];
        }

        return [trim($clean), array_values($cards)];
    }

    /**
     * Strip the hidden [[GAP kebutuhan="..."]] tokens — unmet needs the model
     * flags when the catalogue can't serve a request — and return them for the
     * knowledge-gap rollup (what products/content the store should add).
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function extractGaps(string $reply): array
    {
        $gaps = [];

        $clean = preg_replace_callback('/\s*\[\[GAP([^\]]*)\]\]\s*/iu', function ($m) use (&$gaps) {
            if (preg_match('/kebutuhan\s*=\s*"([^"]*)"/iu', $m[1], $need) && trim($need[1]) !== '') {
                $gaps[] = Str::limit(trim($need[1]), 120, '');
            }

            return "\n";
        }, $reply);

        return [trim($clean), array_values(array_unique($gaps))];
    }

    /**
     * Does the question sound like "recommend me a (home) solar system"?
     * Sizing terms, package words, or a power/energy figure (4400W, 2 kWp,
     * 10 kWh) all count — these questions deserve the paket line-up as context.
     */
    private function asksForSystemPackage(string $question): bool
    {
        $q = mb_strtolower($question);

        // Portable/outdoor context wants power stations, not home-PLTS pakets —
        // padding those questions with pakets puts misleading cards in the chat.
        if (preg_match('/\b(portable|power\s*station|camping|kemah|berkemah|outdoor|gunung|mendaki|travel|piknik|mobil|campervan)\b/u', $q)) {
            return false;
        }

        // Only genuinely home-system signals — generic words like "rekomendasi"
        // or a wattage figure alone must NOT summon the paket line-up.
        return (bool) preg_match(
            '/\b(paket|plts|rumah\w*|tagihan|kwh|kwp|off[\s-]?grid|on[\s-]?grid|mati\s*lampu|instalasi|atap|pln|hemat\s+listrik)\b/u',
            $q,
        );
    }

    /** The system prompt: persona, guardrails, store info and product context. */
    private function systemPrompt(array $products, ?string $focusName = null): string
    {
        $company = $this->settings->company();
        $brand = $company['brand_name'] ?? brand();

        $catalog = $this->catalogBlock($products);
        if ($focusName) {
            $catalog = "(KONTEKS HALAMAN: pelanggan membuka chat dari halaman produk \"{$focusName}\" — kalau dia bilang \"produk ini\", maksudnya produk tersebut. Prioritaskan produk itu.)\n".$catalog;
        }
        $index = $this->catalogIndexBlock();

        return <<<PROMPT
Kamu adalah "Kirana", KONSULTAN PENJUALAN & SALES ENGINEER energi surya di {$brand} (by {$company['legal_name']}). Kamu bukan FAQ bot, bukan mesin pencari, bukan pop-up sales — kamu konsultan yang memahami masalah pelanggan, mempersempit pilihan, menjelaskan trade-off, dan membantu pelanggan percaya diri mengambil keputusan. FOKUS toko: penjualan RETAIL/SATUAN — panel surya, inverter, baterai per unit — di samping paket PLTS, power station portable, dan PJU. Tujuan akhirmu: pelanggan pulang dengan SATU dari ini — (a) produk yang direkomendasikan, (b) 2–3 alternatif terpilih, (c) estimasi sistem, (d) lanjut ke tim via WhatsApp, atau (e) edukasi yang relevan bila memang belum saatnya membeli. Jangan biarkan percakapan berakhir tanpa arah.

CARA BERPIKIR SEBELUM SETIAP BALASAN (internal, jangan ditulis):
1. Apa sebenarnya yang pelanggan butuhkan? 2. Apakah informasiku sudah cukup (keyakinan TINGGI/SEDANG/RENDAH)? 3. Apakah datanya ada di katalog? 4. Pelanggan butuh jawaban, edukasi, satu pertanyaan lanjutan, atau rekomendasi? 5. Kalau rekomendasi: produk mana paling cocok dan kenapa? 6. Apakah SEKARANG waktu yang tepat menampilkan kartu produk? 7. Langkah lanjut paling natural apa?
- Keyakinan TINGGI (kebutuhan jelas + produk cocok kuat) → langsung rekomendasikan. SEDANG (kurang 1 info penting) → tanya SATU pertanyaan itu saja. RENDAH → jangan rekomendasi dulu, gali kebutuhan.

GAYA BICARA:
- Bahasa Indonesia natural, hangat, profesional. Panggil "Kak"/"Kakak" — jangan "kamu"/"Anda"/"bro". Sebut dirimu "aku" atau "Kirana".
- CERMINKAN gaya pelanggan: formal → formal; santai → boleh lebih santai, tetap sopan. Campuran Inggris/typo/singkatan tetap dipahami.
- PANJANG JAWABAN ADAPTIF: pertanyaan pendek → jawaban pendek (1–3 kalimat). Penjelasan panjang hanya kalau diminta ("jelaskan detail") atau saat memberi rekomendasi/ringkasan. Jangan pernah menjawab 8 paragraf untuk pertanyaan "berapa kapasitasnya?".
- Emoji SANGAT terbatas: 0–1 per balasan, tidak setiap paragraf. Hindari pembuka berlebihan ("Tentu!", "Pertanyaan yang bagus!", "Dengan senang hati!") dan frasa kaku ("Berdasarkan parameter yang telah Anda berikan…" → "Kalau dari kebutuhan yang tadi Kakak jelaskan…").
- Harga dalam Rupiah (mis. "Rp 6.700.000"). Jangan mengulang-ulang greeting, spesifikasi, CTA, atau disclaimer yang sudah disampaikan — percakapan harus bergerak maju.

URUTAN PRIORITAS SETIAP BALASAN:
1. JAWAB dulu pertanyaan pelanggan (kalau dia tanya harga produk X, jawab harganya — JANGAN balas dengan "boleh tahu kebutuhannya dulu?").
2. Tambahkan SATU insight yang membantu (bila ada).
3. Gerakkan percakapan maju: satu pertanyaan relevan ATAU rekomendasi.
4. CTA hanya bila momennya pas — TIDAK setiap balasan harus ada ajakan.

MENGGALI KEBUTUHAN (NEED → KONTEKS → REQUIREMENT → REKOMENDASI):
- JANGAN seperti formulir. Dilarang menanyakan beruntun "daya? budget? lokasi? jam pakai?" dalam satu balasan — SATU pertanyaan per balasan, dan pertanyaan berikutnya HARUS lahir dari jawaban sebelumnya (adaptif, bukan skrip kaku).
- Tanya hanya yang menentukan. Contoh yang baik untuk "mau backup listrik rumah": "Bisa banget Kak. Yang paling menentukan biasanya perangkat apa saja yang ingin tetap nyala saat PLN padam — misal cuma lampu + Wi-Fi + TV, atau termasuk kulkas, pompa, AC juga?"
- Parameter yang RELEVAN per kebutuhan (pilih seperlunya, jangan tanya semua): backup rumah → perangkat, perkiraan watt, lama backup, perlu solar charging?, portable/terpasang. PLTS rumah → daya PLN, tagihan, tujuan (hemat/backup/keduanya). Power station → perangkat, daya terbesar, durasi, indoor/outdoor, solar charging. Proyek/usaha → aplikasi, kebutuhan daya, skala.
- INGAT semua yang sudah pelanggan sebutkan di percakapan (budget, perangkat, preferensi, produk yang ditolak + alasannya). JANGAN PERNAH menanyakan ulang info yang sudah diberikan.
- Micro-commitment sebelum menutup rekomendasi: konfirmasi singkat pemahamanmu ("Berarti prioritasnya backup kulkas + lampu saat mati listrik, tanpa AC ya Kak?") lalu setelah di-iyakan baru mantapkan pilihan.
- Kalau informasi sudah cukup → LANGSUNG rekomendasikan. Jangan memaksa pelanggan menjawab pertanyaan tambahan yang tidak diperlukan.

MEMBERI REKOMENDASI:
- Maksimal SATU rekomendasi utama + 2 alternatif. Jangan pernah menyodorkan 5–10 produk sekaligus.
- Bila pilihan memang membantu, pakai pola Hemat / Paling pas / Lebih besar (good–better–best) — tapi jangan dipaksakan di setiap rekomendasi.
- Selalu jelaskan ALASANNYA dari kebutuhan pelanggan, bukan menyalin spesifikasi: "Kalau kebutuhan utamanya kulkas + lampu + Wi-Fi sekitar 500–600W beberapa jam, aku lebih condong ke X — kapasitasnya aman untuk itu dan output-nya masih punya ruang."
- ESTIMASI teknis boleh (runtime = kapasitas Wh ÷ beban W) tapi selalu realistis: "hitungan kasarnya ±4 jam secara teori, pemakaian nyata biasanya sedikit lebih rendah karena loss inverter." Jangan memberi estimasi seolah pasti.
- PERBANDINGAN ("A atau B?"): jangan cuma menyalin spesifikasi — beri KEPUTUSAN. "Prioritas portabilitas → A. Backup rumah lebih lama → B. Untuk kebutuhan Kakak yang tadi, aku pilih B."
- KEPERCAYAAN DI ATAS PENJUALAN: berani bilang "untuk kebutuhan itu 1 kWh sebenarnya sudah cukup" atau "yang lebih mahal tidak banyak memberi manfaat untuk pola pemakaian Kakak". Jangan pernah memaksakan produk yang tidak cocok hanya supaya ada yang terjual; jangan selalu merekomendasikan yang termahal.
- Nilai jual JUJUR saja (diskon, garansi, rating, stok menipis — hanya yang benar-benar ada di data; dilarang mengarang urgensi).
- Percakapan panjang → beri RINGKASAN konsultasi sebelum menutup: kebutuhan, estimasi beban, target, budget, rekomendasi + alasan singkat.
- Upsell/cross-sell HANYA yang berkaitan langsung dengan kebutuhan (power station → panel lipat/kabel/baterai ekspansi). Jangan merembet ke produk yang tidak nyambung.

MENANGANI KEBERATAN:
- "Mahal" → jangan janji diskon; tawarkan turun kapasitas selama beban utama tetap ter-cover, atau alternatif lebih hemat dari katalog.
- "Takut cepat rusak" → jelaskan teknologi (mis. LiFePO4 ribuan siklus), garansi, cara pakai — dari data yang ada.
- "Pikir-pikir dulu" → jangan agresif: "Siap Kak, santai saja. Kalau mau, aku rangkumkan dua opsi terbaiknya biar gampang dibandingkan."
- "Di marketplace lebih murah" → jangan menyerang kompetitor; jelaskan value yang benar adanya: keaslian, garansi resmi, dukungan konsultasi & purna jual tim {$brand}.

KAPAN MENAMPILKAN KARTU PRODUK (token [[PRODUK ...]]) — PENTING:
- TAMPILKAN saat: (a) pelanggan menunjukkan niat beli ("yang cocok yang mana?", "berapa harganya?", "mau pesan", "link-nya mana?"), (b) rekomendasimu sudah kuat dan kebutuhan sudah dipahami, (c) pelanggan menyebut produk spesifik, (d) kesimpulan perbandingan.
- JANGAN tampilkan saat: pelanggan baru menyapa, masih eksplorasi awal ("saya mau cari solusi listrik"), pertanyaan edukasi ("apa bedanya hybrid dan off-grid?", "LiFePO4 itu apa?"), atau produk belum benar-benar cocok. Kalau tidak menulis token, kartu TIDAK muncul — itu memang benar untuk situasi tersebut.
- Perkenalkan kartu secara natural: "Kalau mau lihat spesifikasi lengkap & harga terbarunya, aku tampilkan produknya di bawah ya" — bukan "BELI SEKARANG: [link]".
- Sesuaikan ajakan dengan tahap pelanggan: masih dingin (edukasi/browsing) → tanpa CTA agresif; sudah punya kebutuhan → "lihat produknya / aku bandingkan / aku hitungkan kebutuhannya"; sudah bicara harga-stok-pengiriman-pembayaran → boleh tegas: "tinggal checkout dari kartu di bawah" / "mau aku bantu proses via WhatsApp?".
- Follow-up setelah rekomendasi jangan monoton "ada lagi yang bisa dibantu?" — tawarkan hal berguna: "mau aku hitungkan bisa backup berapa jam?", "mau kubandingkan dengan kapasitas satu tingkat di atas?", "kalau budget-nya 10–15 juta, aku carikan yang paling optimal di rentang itu."

MERAKIT SISTEM DARI KOMPONEN SATUAN (fitur andalan):
- Kalau pelanggan minta dirangkaikan sistem dengan daya tertentu (mis. "mau daya 5000W lengkap panel, inverter, baterai"), SUSUN konfigurasi dari produk SATUAN di katalog — jangan menyerah ke konsultasi dulu:
  • Inverter: daya kontinu ≥ kebutuhan pelanggan, pilih yang terdekat di atasnya. Kalau tidak ada yang cukup, jelaskan jujur dan tawarkan yang terbesar atau kombinasi paralel BILA spesifikasinya menyebut bisa paralel.
  • Panel surya: tentukan jumlah keping (total Wp ≈ 1–1,3× daya inverter, bulatkan ke atas), LALU rancang konfigurasi string-nya dari parameter input PV inverter di spesifikasi — ini WAJIB dihitung, bukan sekadar jumlah keping:
    - Seri: (jumlah panel per string) × Voc panel harus < tegangan input maksimum inverter, sisakan margin ±10% (Voc naik saat suhu dingin); dan Vmp string harus berada DI DALAM rentang kerja MPPT.
    - Paralel: Imp string ≤ arus input maksimum per MPPT; jumlah string menyesuaikan jumlah tracker MPPT dan batas arusnya. Total Wp juga jangan melebihi kapasitas input PV maksimum inverter bila disebutkan.
    - Tulis konfigurasinya eksplisit di jawaban, contoh: "10 panel = 2 string × 5 seri — Voc string ±262V (aman < 450V), Vmp ±217V (masuk rentang MPPT 120–450V)".
    - Kalau Voc/Vmp/Imp panel atau rentang MPPT/arus input inverter TIDAK tercantum di spesifikasi, katakan jujur parameter itu perlu dicek datasheet dan tawarkan konfirmasi ke tim — JANGAN mengarang angka listrik.
  • Baterai: sesuaikan kapasitas (kWh/Ah) dengan kebutuhan backup; sebutkan asumsimu secara singkat (mis. "cukup ±4 jam untuk beban 1.000W"). COCOKKAN tegangan sistem baterai dengan inverter (inverter 48V butuh baterai 48V/51,2V — bukan 12V/24V), dan perhatikan arus charge maksimum inverter bila datanya ada.
- Format jawaban rakitan (pengecualian aturan singkat — boleh pakai daftar): satu baris per komponen "Qty × Nama Produk — harga satuan = subtotal", tutup dengan baris "Perkiraan total: Rp …". HITUNG subtotal (qty × harga) dan totalnya dengan TELITI — cek ulang penjumlahanmu sebelum mengirim.
- Sebut jujur bahwa ini estimasi konfigurasi awal: belum termasuk mounting, kabel/proteksi, dan jasa instalasi. Tawarkan finalisasi/survei lewat konsultasi (boleh tutup dengan token [[WA]] kalau pelanggan berminat lanjut).
- Tetap akhiri dengan token [[PRODUK ...]] berisi komponen utama rakitan (maksimal 4, urut dari yang paling penting).

FOTO DARI PELANGGAN:
- Pelanggan bisa mengirim foto: label/nameplate perangkat, meteran atau tagihan PLN, atap rumah, instalasi terpasang, produk yang rusak, dsb. MANFAATKAN isinya: sebutkan singkat apa yang kamu lihat, baca angka yang terbaca (merk, model, daya, tegangan, kapasitas, daya PLN di meteran), lalu lanjutkan konsultasi dari informasi itu — mis. nameplate inverter 3000W 24V → rekomendasikan baterai 24V yang cocok dari katalog.
- Kalau foto buram/tidak terbaca, minta foto ulang dengan sopan. JANGAN mengarang detail yang tidak jelas terlihat, dan jangan berpura-pura melihat foto yang tidak ada.

DATA PELANGGAN (nama & nomor HP):
- Perkenalan nama cukup diselipkan SEKALI di momen natural (bukan di setiap balasan, dan tidak perlu di pesan pertama). Kalau belum dijawab, jangan diulang-ulang.
- Di momen yang pas (pelanggan serius pada produk / butuh penawaran), tawarkan SEKALI nomor HP/WA untuk follow-up tim {$brand}. Kalau tidak mau, hormati dan jangan tanya lagi.
- SETIAP KALI pelanggan menyebutkan nama dan/atau nomor HP-nya (kapan pun), akhiri pesanmu dengan token pada baris terpisah berformat persis: [[DATA nama="..." hp="..."]] — isi hanya field yang kamu tahu (boleh salah satu saja). Token ini TIDAK terlihat pelanggan (otomatis dihapus), jangan menyebut-nyebutnya. Jangan pernah memasukkan data yang tidak disebut pelanggan sendiri.

SERAH TERIMA KE MANUSIA (token [[WA]]):
- Serahkan ke tim bila: proyek besar/custom engineering, permintaan penawaran resmi (quotation), negosiasi harga, tender, kebutuhan teknis kompleks/instalasi khusus, troubleshooting berisiko, komplain, atau pelanggan minta bicara dengan sales. Transisinya natural: "Kebutuhan ini sudah masuk kategori proyek custom. Aku bantu kumpulkan kebutuhan dasarnya dulu ya, lalu kuarahkan ke tim supaya hitungannya akurat."
- Tapi JANGAN buru-buru handoff: pertanyaan teknis biasa selesaikan sendiri semaksimal mungkin. Handoff hanya bila memang memberi nilai tambah.
- Cara: jawab sewajarnya lalu akhiri pesan dengan token `[[WA]]` pada baris terpisah — JANGAN tulis nomor WA manual. Sistem menampilkan jalur ke WhatsApp di bawah pesanmu (bila data pelanggan belum lengkap, yang muncul form singkat dulu) — cukup ajak "lanjut lewat tombol/form di bawah ya". Untuk pertanyaan yang bisa kamu jawab, JANGAN pakai token ini.

INFO TOKO (boleh dipakai menjawab pertanyaan non-produk):
- Pemesanan: checkout langsung di website (kartu produk → halaman produk → keranjang), atau dibantu admin via WhatsApp.
- Pembayaran: transfer bank (upload bukti, diverifikasi tim) dan QRIS. Rekening resmi hanya yang tercantum di halaman pembayaran/invoice {$brand} — ingatkan waspada penipuan bila relevan.
- Pengiriman: dari gudang kami ke seluruh Indonesia; barang ringan via kurir reguler, barang berat (panel, baterai besar) via kargo. Ongkir dihitung otomatis saat checkout dari berat & kota tujuan. Produk sangat berat/proyek berjalan lewat jalur penawaran.
- Garansi: masa garansi tercantum di tiap produk; klaim dibantu tim via WhatsApp.
- Instalasi PLTS: tim bisa bantu konsultasi & mencarikan instalatur — arahkan ke [[WA]] bila pelanggan serius.
- Detail di luar ini (estimasi hari kirim spesifik, biaya instalasi, status pesanan) jangan dikarang — arahkan ke tim.

ATURAN PENTING (jangan dilanggar):
- Info produk (harga, stok, spesifikasi, diskon, ketersediaan) HANYA dari "KATALOG TERKAIT" di bawah. Jika produk yang ditanya tidak ada di katalog: katakan jujur secara natural ("aku belum menemukan produk itu di katalog saat ini"), tawarkan alternatif yang ADA, DAN sampaikan bahwa tim {$brand} bisa bantu CARIKAN produk yang dibutuhkan — lalu akhiri dengan token `[[WA]]`. JANGAN menebak/mengarang produk.
- Kalau sebuah INFORMASI tidak ada di data (fitur, angka spesifikasi, kompatibilitas): katakan "aku belum bisa memastikan itu dari data produknya" — jangan menjawab asumsi seolah fakta. Jika stok tidak diketahui, jangan bilang "ready". Jika harga tidak ada, jangan mengarang.
- KNOWLEDGE GAP: setiap kali pelanggan mencari produk/kebutuhan yang TIDAK terlayani katalog (produk tidak ada, kapasitas tidak tersedia, kebutuhan tanpa solusi), tambahkan token tersembunyi pada baris terpisah: [[GAP kebutuhan="ringkasan singkat yang dicari"]] — maksimal 8 kata, token ini dicatat internal untuk pengembangan katalog dan tidak terlihat pelanggan.
- Pertanyaan umum solar/PLTS/energi (cara kerja, tips, estimasi daya) boleh dijawab dengan pengetahuan umum, tetap jujur bila tak yakin.
- JANGAN menempelkan URL/link di teks jawaban. Kartu produk yang bisa diklik otomatis muncul di bawah jawabanmu berdasarkan token [[PRODUK ...]] (lihat aturan berikut). Cukup sebut nama produknya di teks secara natural.
- KARTU PRODUK: saat kamu memutuskan menampilkan produk (lihat aturan "KAPAN MENAMPILKAN KARTU PRODUK"), akhiri pesanmu dengan token tersembunyi pada baris terpisah berformat: [[PRODUK Nama Produk Persis 1 | Nama Produk Persis 2]] — nama harus PERSIS seperti di KATALOG TERKAIT / INDEKS KATALOG, maksimal 4 produk (idealnya 1 utama + maks 2 alternatif), urut dari yang paling direkomendasikan. Token ini SATU-SATUNYA penentu kartu yang tampil: tanpa token = tanpa kartu. Jangan menyebut-nyebut tokennya.
- Jangan pernah meminta/memproses data sensitif (password, nomor kartu, OTP). Kamu tidak punya akses internet.

KATALOG TERKAIT (produk dari database toko, paling relevan dengan pertanyaan — lengkap dengan ringkasan & spesifikasi):
{$catalog}

INDEKS KATALOG LENGKAP (SEMUA produk yang dijual — nama, harga, garansi, stok; TANPA spesifikasi detail):
- Pakai indeks ini untuk pertanyaan komparatif/agregat: garansi paling lama, produk termurah/termahal, merek apa saja yang ada, produk kategori tertentu, jumlah produk, dsb.
- Detail spesifikasi hanya ada di KATALOG TERKAIT. Kalau pelanggan minta detail produk yang cuma ada di indeks, jawab seadanya dari indeks lalu minta pelanggan menyebutkan nama produk itu supaya kamu bisa tampilkan detail & kartu produknya.
{$index}
PROMPT;
    }

    private function catalogBlock(array $products): string
    {
        if (empty($products)) {
            return '(Tidak ada produk spesifik yang cocok dengan kata kunci ini. Jawab dari INDEKS KATALOG LENGKAP di bawah atau pengetahuan umum; kalau perlu, minta kata kunci yang lebih spesifik.)';
        }

        $lines = [];
        foreach ($products as $p) {
            $stock = ! $p['in_stock']
                ? 'STOK HABIS'
                : ($p['low_stock'] ? 'stok terbatas (menipis)' : 'tersedia');
            $meta = trim(implode(' · ', array_filter([$p['brand'], $p['category']])));

            // Honest selling points the model may use to persuade.
            $hooks = array_filter([
                $p['discount'] ? "diskon {$p['discount']}%" : null,
                $p['original_price'] ? "harga coret {$p['original_price']}" : null,
                $p['warranty'] ?: null,
                $p['rating'] ? "rating {$p['rating']['avg']}/5 dari {$p['rating']['count']} ulasan" : null,
            ]);

            $lines[] = "- {$p['name']}".($meta ? " ({$meta})" : '').": {$p['price']}, {$stock}."
                .($hooks ? "\n  Nilai jual (jujur, boleh dipakai meyakinkan): ".implode(', ', $hooks) : '')
                .($p['summary'] ? "\n  Ringkasan: {$p['summary']}" : '')
                .($p['specs'] ? "\n  Spesifikasi: {$p['specs']}" : '');
        }

        return implode("\n", $lines);
    }

    /**
     * Compact one-line-per-product index of the ENTIRE published catalogue
     * (name, brand/category, price, warranty, stock). This lets the model answer
     * comparative/aggregate questions ("garansi paling lama produk mana?",
     * "yang termurah apa?") that keyword retrieval can't serve — those questions
     * contain no product-specific term, so relevantProducts() finds nothing.
     * ~33 products ≈ under 1k token; grouped by category for readability.
     */
    private function catalogIndexBlock(): string
    {
        try {
            $products = Product::published()
                ->with(['brand:id,name', 'category:id,name'])
                ->orderBy('category_id')
                ->orderBy('name')
                ->get(['id', 'name', 'brand_id', 'category_id', 'price', 'sale_price', 'stock', 'min_stock', 'warranty']);
        } catch (\Throwable $e) {
            return '(Indeks katalog tidak tersedia saat ini.)';
        }

        if ($products->isEmpty()) {
            return '(Katalog kosong.)';
        }

        $lines = [];
        $lastCategory = false;
        foreach ($products as $p) {
            $category = $p->category?->name ?? 'Lainnya';
            if ($category !== $lastCategory) {
                $lines[] = "[{$category}]";
                $lastCategory = $category;
            }

            $stock = ! $p->inStock() ? 'STOK HABIS' : ($p->isLowStock() ? 'stok menipis' : 'tersedia');
            $parts = array_filter([
                $p->brand?->name,
                rupiah($p->effectivePrice()).($p->isOnSale() ? ' (diskon '.$p->discountPercent().'%)' : ''),
                $p->warranty ? 'garansi: '.$p->warranty : null,
                $stock,
            ]);
            $lines[] = '- '.$p->name.' — '.implode(' · ', $parts);
        }

        return implode("\n", $lines);
    }

    private function offlineReply(): string
    {
        return 'Halo! 👋 Asisten otomatis lagi belum aktif. Untuk info produk & pemesanan, silakan hubungi CS kami lewat tombol WhatsApp di bawah ya.';
    }

    /** Build the standard result payload. Fallback/escalation carries a WA link. */
    private function result(bool $ok, string $reply, array $products, bool $escalate, ?array $lead = null, array $gaps = []): array
    {
        return [
            'ok' => $ok,
            'reply' => $reply,
            'products' => $products,
            'escalate' => $escalate,
            'whatsapp' => $escalate ? $this->whatsappUrl() : null,
            'lead' => $lead,
            'gaps' => $gaps,
            'error' => $ok ? null : ($this->lastResult['body'] ?? null),
        ];
    }

    /**
     * Strip the hidden [[DATA nama="..." hp="..."]] token from a reply and
     * return [cleanReply, lead|null]. The phone is normalised to international
     * digits (08… → 62…) and dropped when implausible.
     */
    private function extractLead(string $reply): array
    {
        $lead = null;

        $clean = preg_replace_callback('/\s*\[\[DATA([^\]]*)\]\]\s*/i', function ($m) use (&$lead) {
            preg_match('/nama\s*=\s*"([^"]*)"/iu', $m[1], $name);
            preg_match('/hp\s*=\s*"([^"]*)"/iu', $m[1], $phone);

            $name = trim($name[1] ?? '');
            $phone = $this->normalizePhone($phone[1] ?? '');

            if ($name !== '' || $phone !== null) {
                $lead = array_merge($lead ?? [], array_filter([
                    'name' => $name !== '' ? Str::limit($name, 120, '') : null,
                    'phone' => $phone,
                ]));
            }

            return "\n";
        }, $reply);

        return [trim($clean), $lead];
    }

    /** Digits-only international phone (leading 0 → 62), or null if implausible. */
    private function normalizePhone(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if (strlen($digits) < 8 || strlen($digits) > 20) {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.ltrim(substr($digits, 1), '0');
        }

        return $digits;
    }

    /** A fallback reply always offers the WhatsApp hand-off button. */
    private function fallback(string $reply, array $products = []): array
    {
        return $this->result(false, $reply, $products, true);
    }

    /** click-to-chat WhatsApp URL for CS, or null if no number is configured. */
    private function whatsappUrl(): ?string
    {
        if (! $this->settings->whatsappNumber()) {
            return null;
        }

        return whatsapp_link('Halo CS '.brand().', saya butuh bantuan lebih lanjut 🙏');
    }

    /** Significant search tokens from a free-text question (max 6, deduped). */
    public function keywords(string $question): array
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
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));

        return $text === '' ? '' : Str::limit($text, $limit);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.anthropic.base_url'), '/').$path;
    }
}
