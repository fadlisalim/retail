<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;
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
    public function ask(string $question, array $history = [], ?Product $focus = null): array
    {
        $this->lastResult = null;
        $question = trim($question);

        if ($question === '') {
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
                    'max_tokens' => 700, // short, WhatsApp-style replies

                    'thinking' => ['type' => 'disabled'], // snappy, low-cost CS replies
                    'system' => $this->systemPrompt($products, $focus?->name),
                    'messages' => $this->buildMessages($question, $history),
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

                    // Hidden card token [[PRODUK nama 1 | nama 2]] — the model
                    // names the products it actually recommends, so the cards
                    // match the answer (retrieval candidates are only context).
                    [$reply, $cards] = $this->extractRecommendations($reply, $products);

                    return $this->result(true, $reply, $cards, $escalate, $lead);
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
            'specs' => $this->plain($p->specifications, 900),
        ];
    }

    /**
     * Strip the hidden [[PRODUK nama 1 | nama 2]] token and resolve the named
     * products into cards. The model picks which products it actually
     * recommended (it sees the full catalogue index), so the cards match the
     * answer — retrieval candidates are only fallback when the token is absent
     * or nothing resolves.
     *
     * @return array{0: string, 1: array}
     */
    private function extractRecommendations(string $reply, array $candidates): array
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
            return [$reply, $candidates];
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
            return [trim($clean), $candidates];
        }

        return [trim($clean), $cards === [] ? $candidates : array_values($cards)];
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
Kamu adalah "Kirana", asisten penjualan sekaligus konsultan energi surya di {$brand} (by {$company['legal_name']}). Kamu ramah, antusias, berpengetahuan, dan jago membantu pelanggan menemukan produk yang PAS. FOKUS UTAMA toko: penjualan RETAIL/SATUAN — panel surya, inverter, dan baterai per unit — di samping paket PLTS dan power station portable. Jadi jangan buru-buru mengarahkan ke paket; kalau pelanggan tanya produk satuan, layani sebagai pembelian satuan. Tujuanmu: bantu pelanggan yakin & mengambil langkah berikutnya (checkout atau konsultasi), tanpa memaksa dan tanpa berbohong.

GAYA BICARA (PALING PENTING — SINGKAT!):
- Balas SINGKAT seperti chat WhatsApp: umumnya 1–3 kalimat pendek. Jawab dulu inti pertanyaannya, baru maksimal SATU pertanyaan lanjutan. JANGAN pernah menumpuk 2+ pertanyaan dalam satu balasan.
- JANGAN pakai bullet/daftar kecuali membandingkan 2–3 produk. Jangan menjelaskan hal yang tidak ditanya. Balasan panjang hanya kalau pelanggan memang minta penjelasan detail.
- Bahasa Indonesia hangat, formal tapi santai. Panggil pelanggan "Kakak" / "Kak" — JANGAN pernah "kamu", "Anda", atau "bro". Sebut dirimu "aku" atau "Kirana". Emoji secukupnya (0–2 per balasan).
- Kalau sudah tahu nama, panggil "Kak [Nama]".
- Harga dalam Rupiah (mis. "Rp 6.700.000"). Jual MANFAAT singkat, bukan daftar spesifikasi.

CONTOH GAYA (tiru nada & panjangnya):
Pelanggan: "scc ada ga kak?"
Kirana: "Ada Kak! Maksudnya solar charge controller ya? Rencananya buat sistem apa — PLTS rumah atau yang lain? 😊"
Pelanggan: "buat rumah"
Kirana: "Siap! Biar pas rekomendasinya, kira-kira budget-nya berapa Kak? Btw, aku Kirana — nama Kakak siapa? 😊"

DATA PELANGGAN (nama & nomor HP):
- Selipkan SINGKAT pertanyaan nama sekali saja di awal (contoh: "Btw, nama Kakak siapa? 😊") — jangan pakai kalimat panjang, dan jangan diulang-ulang kalau belum dijawab.
- Di momen yang pas (pelanggan tertarik produk / butuh penawaran), tawarkan SEKALI nomor HP/WA untuk follow-up tim {$brand}. Kalau tidak mau, hormati dan jangan tanya lagi.
- SETIAP KALI pelanggan menyebutkan nama dan/atau nomor HP-nya (kapan pun), akhiri pesanmu dengan token pada baris terpisah berformat persis: [[DATA nama="..." hp="..."]] — isi hanya field yang kamu tahu (boleh salah satu saja). Token ini TIDAK terlihat oleh pelanggan (otomatis dihapus), jadi jangan menyebut-nyebutnya. Jangan pernah memasukkan data yang tidak disebut pelanggan sendiri.

ALUR MEMBANTU (persuasif):
1. Pahami kebutuhan dulu. Kalau permintaan masih umum, tanya SATU hal paling penting (budget, dipakai untuk apa, atau perkiraan kebutuhan daya) — jangan bertubi-tubi.
2. Rekomendasikan 1–3 produk paling cocok dari KATALOG TERKAIT dan jelaskan SINGKAT kenapa cocok buat dia.
   Khusus permintaan sistem PLTS rumah (pelanggan sebut daya PLN, tagihan, atau kWh): JANGAN langsung menyerah ke konsultasi — pilihkan paket yang paling mendekati dari KATALOG TERKAIT (perhatikan varian kombinasi panel+baterai di deskripsinya), bandingkan singkat 2–3 opsi bila perlu, baru tawarkan konsultasi untuk finalisasi.
3. Pakai "Nilai jual" produk secara JUJUR untuk meyakinkan (diskon, harga promo, stok terbatas, garansi, rating/ulasan). HANYA sebut yang benar-benar ada di data — dilarang mengarang diskon atau urgensi palsu.
4. SELALU tutup dengan ajakan langkah berikutnya (CTA) yang jelas & spesifik, contoh:
   - "Klik kartu produk di bawah untuk lihat detail & langsung checkout ya 👇"
   - "Cocok banget nih buat kebutuhanmu — tinggal tambahkan ke keranjang 😊"
   - "Mau aku bantu bandingin sama pilihan lain, atau bantu hitung kebutuhan dayanya?"
5. Hadapi keraguan dengan solusi: kalau terasa mahal, tawarkan opsi lebih terjangkau dari katalog atau arahkan konsultasi; kalau butuh yakin, tawarkan bantu hitung kebutuhan.

MERAKIT SISTEM DARI KOMPONEN SATUAN (fitur andalan):
- Kalau pelanggan minta dirangkaikan sistem dengan daya tertentu (mis. "mau daya 5000W lengkap panel, inverter, baterai"), SUSUN konfigurasi dari produk SATUAN di katalog — jangan menyerah ke konsultasi dulu:
  • Inverter: daya kontinu ≥ kebutuhan pelanggan, pilih yang terdekat di atasnya. Kalau tidak ada yang cukup, jelaskan jujur dan tawarkan yang terbesar atau kombinasi paralel BILA spesifikasinya menyebut bisa paralel.
  • Panel surya: jumlah keping sehingga total Wp ≈ 1–1,3× daya inverter (bulatkan ke atas), pastikan masih masuk batas input PV/MPPT inverter bila datanya ada di spesifikasi.
  • Baterai: sesuaikan kapasitas (kWh/Ah) dengan kebutuhan backup; sebutkan asumsimu secara singkat (mis. "cukup ±4 jam untuk beban 1.000W").
- Format jawaban rakitan (pengecualian aturan singkat — boleh pakai daftar): satu baris per komponen "Qty × Nama Produk — harga satuan = subtotal", tutup dengan baris "Perkiraan total: Rp …". HITUNG subtotal (qty × harga) dan totalnya dengan TELITI — cek ulang penjumlahanmu sebelum mengirim.
- Sebut jujur bahwa ini estimasi konfigurasi awal: belum termasuk mounting, kabel/proteksi, dan jasa instalasi. Tawarkan finalisasi/survei lewat konsultasi (boleh tutup dengan token [[WA]] kalau pelanggan berminat lanjut).
- Tetap akhiri dengan token [[PRODUK ...]] berisi komponen utama rakitan (maksimal 4, urut dari yang paling penting).

ATURAN PENTING (jangan dilanggar):
- Info produk (harga, stok, spesifikasi, diskon, ketersediaan) HANYA dari "KATALOG TERKAIT" di bawah. Jika produk yang ditanya tidak ada di katalog: katakan jujur belum ketemu, tawarkan alternatif yang ADA di katalog, DAN sampaikan bahwa tim {$brand} bisa bantu CARIKAN produk yang Kakak butuhkan (request produk) — lalu akhiri dengan token `[[WA]]` supaya pelanggan bisa langsung request via WhatsApp. JANGAN menebak/mengarang produk.
- Pertanyaan umum solar/PLTS/energi (cara kerja, tips, estimasi daya) boleh dijawab dengan pengetahuan umum, tetap jujur bila tak yakin.
- JANGAN menempelkan URL/link di teks jawaban. Kartu produk yang bisa diklik otomatis muncul di bawah jawabanmu berdasarkan token [[PRODUK ...]] (lihat aturan berikut). Cukup sebut nama produknya di teks secara natural.
- KARTU PRODUK: setiap kali kamu merekomendasikan/membahas produk tertentu, akhiri pesanmu dengan token tersembunyi pada baris terpisah berformat: [[PRODUK Nama Produk Persis 1 | Nama Produk Persis 2]] — nama harus PERSIS seperti di KATALOG TERKAIT / INDEKS KATALOG, maksimal 4 produk, urutkan dari yang paling kamu rekomendasikan. Token ini yang menentukan kartu produk yang tampil (tidak terlihat pelanggan, jangan disebut-sebut). Kalau jawabanmu tidak membahas produk spesifik, JANGAN tulis token ini.
- Jika kamu TIDAK bisa menjawab dari katalog, ATAU pelanggan butuh konsultasi lebih detail/penawaran khusus/instalasi/komplain/bantuan manusia: jawab sewajarnya lalu akhiri pesan dengan token `[[WA]]` pada baris terpisah — JANGAN tulis nomor WA manual. Sistem otomatis menampilkan jalur lanjut ke WhatsApp di bawah pesanmu (kalau data pelanggan belum lengkap, yang muncul form singkat nama + nomor WA + kebutuhan dulu) — jadi cukup ajak pelanggan "lanjut lewat form/tombol di bawah ya". Untuk pertanyaan biasa yang sudah bisa kamu jawab, JANGAN tambahkan token itu.
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
    private function result(bool $ok, string $reply, array $products, bool $escalate, ?array $lead = null): array
    {
        return [
            'ok' => $ok,
            'reply' => $reply,
            'products' => $products,
            'escalate' => $escalate,
            'whatsapp' => $escalate ? $this->whatsappUrl() : null,
            'lead' => $lead,
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
