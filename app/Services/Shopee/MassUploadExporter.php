<?php

namespace App\Services\Shopee;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Ekspor katalog ke template "Shopee Mass Upload (basic)".
 *
 * Server tidak punya library spreadsheet, jadi file dibuat dengan menyalin
 * template asli Shopee lalu mengganti XML sheet "Template": baris 1–6 (header
 * & petunjuk Shopee) dipertahankan, data ditulis mulai baris 7 sebagai
 * inline string / angka. Satu baris = satu produk, atau satu varian untuk
 * produk bervarian (Kode Integrasi Variasi = SKU induk).
 *
 * Aturan Shopee yang dipatuhi: nama 5–255, deskripsi 20–3000 (teks polos),
 * nama variasi ≤ 14, nama pilihan ≤ 20, harga 99–150 jt, foto berupa link
 * publik, berat dalam gram, dimensi cm (isi ketiganya atau kosong semua).
 * Pelanggaran yang tidak bisa diperbaiki otomatis dicatat di $warnings.
 */
class MassUploadExporter
{
    /** Kolom sheet "Template" (A..AM) sesuai baris 1 template. */
    public const COLUMNS = [
        'ps_category', 'ps_product_name', 'ps_product_description',
        'ps_maximum_purchase_quantity', 'ps_maximum_purchase_quantity_start_date', 'ps_maximum_purchase_quantity_time_period', 'ps_maximum_purchase_quantity_end_date',
        'ps_minimum_purchase_quantity', 'ps_sku_parent_short', 'ps_dangerous_goods',
        'et_title_variation_integration_no', 'et_title_variation_1', 'et_title_option_for_variation_1', 'et_title_image_per_variation',
        'et_title_variation_2', 'et_title_option_for_variation_2',
        'ps_price', 'ps_stock', 'ps_sku_short', 'ps_new_size_chart', 'et_title_size_chart', 'ps_gtin_code',
        'ps_item_cover_image', 'ps_item_image_1', 'ps_item_image_2', 'ps_item_image_3', 'ps_item_image_4', 'ps_item_image_5', 'ps_item_image_6', 'ps_item_image_7', 'ps_item_image_8',
        'ps_weight', 'ps_length', 'ps_width', 'ps_height',
        'channel_id.8003', 'channel_id.8005', 'ps_product_pre_order_dts', 'et_title_reason',
    ];

    private const FIRST_DATA_ROW = 7;

    /** @var list<string> */
    public array $warnings = [];

    /** @var list<string> */
    public array $skipped = [];

    /**
     * Susun baris data (array asosiatif per kolom) untuk semua produk yang
     * layak dijual di Shopee.
     *
     * @return list<array<string, string|int|float|null>>
     */
    public function rows(): array
    {
        $this->warnings = [];
        $this->skipped = [];
        $rows = [];

        $products = Product::with(['variants', 'images', 'brand', 'category'])
            ->where('status', 'published')
            ->orderBy('id')
            ->get();

        foreach ($products as $product) {
            if (! $product->is_purchasable || $product->requires_quotation) {
                $this->skipped[] = $product->sku.' — hanya penawaran / tidak dijual online';

                continue;
            }
            if ($product->pickup_only) {
                $this->skipped[] = $product->sku.' — ambil di lokasi saja';

                continue;
            }
            if (! $product->main_image_path) {
                // Shopee: sampul wajib agar tayang, tapi boleh dilengkapi belakangan di Seller Centre.
                $this->warnings[] = $product->sku.': belum punya foto sampul — produk masuk sebagai belum siap tayang, lengkapi fotonya di Shopee';
            }

            $variants = $product->product_type === 'variable'
                ? $product->variants->where('is_active', true)->values()
                : collect();

            if ($variants->count() > 20) {
                $this->warnings[] = $product->sku.': '.$variants->count().' varian, Shopee maks. 20 untuk 1 level variasi';
            }

            if ($variants->isEmpty()) {
                $row = array_replace($this->baseRow($product), $this->priceRow($product, null));
                $this->checkPrice($product->sku, [$row['ps_price']]);
                $rows[] = $row;

                continue;
            }

            $prices = [];
            $base = $this->baseRow($product); // sekali per produk (peringatan teks tidak berulang per varian)
            $options = $this->variationOptions($product, $variants);
            $variationName = $this->variationName($product, $variants);
            $allHaveImages = $variants->every(fn (ProductVariant $v) => (bool) $v->image_path);

            foreach ($variants as $i => $variant) {
                $row = array_replace($base, $this->priceRow($product, $variant));
                $row['et_title_variation_integration_no'] = $product->sku;
                $row['et_title_variation_1'] = $variationName;
                $row['et_title_option_for_variation_1'] = $options[$i];
                $row['et_title_image_per_variation'] = $allHaveImages ? asset('storage/'.$variant->image_path) : null;
                $prices[] = $row['ps_price'];
                $rows[] = $row;
            }
            $this->checkPrice($product->sku, $prices);
        }

        return $rows;
    }

    /** Tulis file .xlsx dari template. Mengembalikan jumlah baris data. */
    public function write(string $outPath): int
    {
        $template = config('shopee.template');
        if (! is_file($template)) {
            throw new RuntimeException('Template Shopee tidak ditemukan: '.$template);
        }

        $rows = $this->rows();

        if (! copy($template, $outPath)) {
            throw new RuntimeException('Tidak bisa menulis '.$outPath);
        }

        $zip = new ZipArchive;
        if ($zip->open($outPath) !== true) {
            throw new RuntimeException('Template Shopee bukan file xlsx yang valid.');
        }

        $sheetPath = $this->templateSheetPath($zip);
        $xml = $zip->getFromName($sheetPath);
        if ($xml === false || ! str_contains($xml, '</sheetData>')) {
            throw new RuntimeException('Sheet "Template" tidak ditemukan di template Shopee.');
        }

        $body = '';
        foreach ($rows as $i => $row) {
            $body .= $this->rowXml(self::FIRST_DATA_ROW + $i, $row);
        }
        $lastRow = self::FIRST_DATA_ROW + max(0, count($rows) - 1);
        $lastCol = $this->columnLetter(count(self::COLUMNS));

        $xml = str_replace('</sheetData>', $body.'</sheetData>', $xml);
        $xml = preg_replace('/<dimension ref="[^"]*"/', '<dimension ref="A1:'.$lastCol.$lastRow.'"', $xml, 1);

        $zip->addFromString($sheetPath, $xml);
        $zip->close();

        return count($rows);
    }

    /** @return array<string, string|int|float|null> */
    private function baseRow(Product $product): array
    {
        $row = array_fill_keys(self::COLUMNS, null);
        $limits = config('shopee.limits');

        $row['ps_category'] = $this->categoryCode($product);
        $row['ps_product_name'] = $this->limit($this->productName($product), $limits['name_max'], $product->sku.': nama produk dipotong ke '.$limits['name_max'].' karakter');
        $row['ps_product_description'] = $this->description($product);
        $row['ps_minimum_purchase_quantity'] = max(1, (int) $product->min_purchase) > 1 ? (int) $product->min_purchase : null;
        $row['ps_sku_parent_short'] = $product->sku;
        $row['ps_dangerous_goods'] = preg_match(config('shopee.dangerous_pattern'), $product->name.' '.($product->category?->name ?? '')) ? 'Yes (ID)' : 'No (ID)';
        $row['ps_item_cover_image'] = $product->main_image_path ? asset('storage/'.$product->main_image_path) : null;

        $gallery = $product->images
            ->filter(fn ($img) => ! $img->video_path && $img->path && $img->path !== $product->main_image_path)
            ->take($limits['images_max'])->values();
        foreach ($gallery as $i => $img) {
            $row['ps_item_image_'.($i + 1)] = asset('storage/'.$img->path);
        }

        return $row;
    }

    /**
     * Harga, stok, kode, berat, dimensi, jasa kirim — dari varian bila ada.
     *
     * @return array<string, string|int|float|null>
     */
    private function priceRow(Product $product, ?ProductVariant $variant): array
    {
        $price = (int) round($variant ? $variant->effectivePrice() : $product->effectivePrice());
        $weight = $variant ? $variant->weightGrams() : (int) $product->weight_grams;
        [$l, $w, $h] = $variant
            ? [$variant->lengthCm(), $variant->widthCm(), $variant->heightCm()]
            : [(float) $product->length_cm, (float) $product->width_cm, (float) $product->height_cm];
        $hasDims = $l > 0 && $w > 0 && $h > 0;

        if ($weight <= 0) {
            $this->warnings[] = ($variant?->sku ?? $product->sku).': berat kosong — Shopee mewajibkan berat';
        }

        $row = [
            'ps_price' => $price,
            'ps_stock' => max(0, (int) ($variant ? $variant->stock : $product->stock)),
            'ps_sku_short' => $variant?->sku ?? $product->sku,
            'ps_weight' => max(0, $weight),
            'ps_length' => $hasDims ? $this->num($l) : null,
            'ps_width' => $hasDims ? $this->num($w) : null,
            'ps_height' => $hasDims ? $this->num($h) : null,
        ];

        foreach (config('shopee.channels') as $id => $channel) {
            $max = $channel['max_grams'] ?? null;
            $row['channel_id.'.$id] = ($max === null || $weight <= $max) ? 'Aktif' : 'Nonaktif';
        }

        return $row;
    }

    private function productName(Product $product): string
    {
        $name = trim((string) $product->name);
        $brand = trim((string) ($product->brand?->name ?? ''));
        if ($brand !== '' && ! Str::contains(Str::lower($name), Str::lower($brand))) {
            $name = $brand.' '.$name;
        }

        return $name;
    }

    /** Deskripsi + spesifikasi sebagai teks polos (Shopee tidak menerima HTML). */
    private function description(Product $product): string
    {
        $parts = array_filter([
            $this->htmlToText((string) $product->description),
            $this->htmlToText((string) $product->specifications) !== '' ? "SPESIFIKASI\n".$this->htmlToText((string) $product->specifications) : '',
            $product->warranty ? 'Garansi: '.$product->warranty : '',
        ]);
        $text = trim(implode("\n\n", $parts));

        if ($text === '') {
            $text = trim((string) $product->short_description);
        }
        if (mb_strlen($text) < 20) {
            $text = rtrim($text."\n".$product->name.' — hubungi kami untuk detail produk.');
        }

        return $this->limit($text, config('shopee.limits.description_max'), $product->sku.': deskripsi dipotong ke 3000 karakter');
    }

    public function htmlToText(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = preg_replace('/<\s*(script|style)[^>]*>.*?<\/\s*\1\s*>/is', '', $html);
        // Tabel spesifikasi: "Label: nilai" per baris.
        $html = preg_replace('/<\s*\/?\s*(thead|tbody|table)[^>]*>/i', '', $html);
        $html = preg_replace('/<\s*\/\s*t[hd]\s*>\s*<\s*t[hd][^>]*>/i', ': ', $html);
        $html = preg_replace('/<\s*\/\s*tr\s*>/i', "\n", $html);
        $html = preg_replace('/<\s*li[^>]*>/i', '• ', $html);
        $html = preg_replace('/<\s*(br|\/p|\/div|\/li|\/h[1-6]|\/ul|\/ol)[^>]*>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{A0}", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ *\n */', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function variationName(Product $product, $variants): string
    {
        $key = array_key_first((array) $variants->first()->option_values) ?? 'Varian';
        $max = config('shopee.limits.variation_name_max');
        if (mb_strlen($key) > $max) {
            $this->warnings[] = $product->sku.": nama variasi \"{$key}\" dipotong ke {$max} karakter";
        }

        return mb_substr($key, 0, $max);
    }

    /**
     * Nama pilihan per varian (≤ 20 karakter, unik dalam satu produk).
     *
     * @return list<string>
     */
    private function variationOptions(Product $product, $variants): array
    {
        $max = config('shopee.limits.variation_option_max');
        $labels = $variants->map(function (ProductVariant $variant) {
            $values = array_values((array) $variant->option_values);
            $label = trim((string) ($values[0] ?? ''));

            return $label !== '' ? $label : trim((string) $variant->name);
        });
        // Label kepanjangan: pakai bagian sebelum " · " / " — " / " (" (mis. "ECHO-16 · 10.000W / 16 kWh"
        // → "ECHO-16") selama hasilnya unik; kalau tidak, dipotong.
        $heads = $labels->map(fn ($l) => trim(preg_split('/\s+(·|—|-|\()\s*/u', $l, 2)[0]));
        $useHeads = $heads->unique()->count() === $labels->count() && $heads->every(fn ($h) => $h !== '' && mb_strlen($h) <= $max);

        $options = [];
        foreach ($labels as $label) {
            if (mb_strlen($label) > $max) {
                $short = $useHeads
                    ? trim(preg_split('/\s+(·|—|-|\()\s*/u', $label, 2)[0])
                    : trim(mb_substr(str_replace([' · ', ' — '], ['/', ' '], $label), 0, $max));
                $this->warnings[] = $product->sku.": pilihan varian \"{$label}\" dipersingkat jadi \"{$short}\"";
                $label = $short;
            }
            $base = $label;
            $n = 2;
            while (in_array($label, $options, true)) {
                $label = mb_substr($base, 0, $max - 2).' '.$n++;
            }
            $options[] = $label;
        }

        return $options;
    }

    private function categoryCode(Product $product): ?string
    {
        $slug = (string) ($product->category?->slug ?? '');
        foreach (config('shopee.categories', []) as $prefix => $code) {
            if ($slug === $prefix || str_starts_with($slug, $prefix.'-')) {
                return (string) $code;
            }
        }

        return null;
    }

    /** @param list<int> $prices */
    private function checkPrice(string $sku, array $prices): void
    {
        $limits = config('shopee.limits');
        $min = min($prices);
        $max = max($prices);
        if ($min < $limits['price_min'] || $max > $limits['price_max']) {
            $this->warnings[] = $sku.': harga di luar batas Shopee (Rp '.number_format($limits['price_min'], 0, ',', '.').' – Rp '.number_format($limits['price_max'], 0, ',', '.').') — Shopee akan menolak produk ini';
        }
        if ($min > 0 && $max / $min > $limits['price_ratio']) {
            $this->warnings[] = $sku.': rasio harga varian termahal/termurah '.number_format($max / $min, 1, ',', '.').'× melebihi batas '.$limits['price_ratio'].'× — pisahkan jadi produk terpisah di Shopee';
        }
    }

    private function limit(string $text, int $max, string $warning): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $this->warnings[] = $warning;

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private function num(float $n): float|int
    {
        return floor($n) == $n ? (int) $n : round($n, 2);
    }

    /** @param array<string, string|int|float|null> $row */
    private function rowXml(int $r, array $row): string
    {
        $cells = '';
        foreach (self::COLUMNS as $i => $key) {
            $value = $row[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $ref = $this->columnLetter($i + 1).$r;
            $cells .= is_int($value) || is_float($value)
                ? '<c r="'.$ref.'"><v>'.$value.'</v></c>'
                : '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
        }

        return '<row r="'.$r.'">'.$cells.'</row>';
    }

    private function columnLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + $n % 26).$s;
            $n = intdiv($n, 26);
        }

        return $s;
    }

    /** Cari path XML sheet bernama "Template" lewat workbook.xml + rels. */
    private function templateSheetPath(ZipArchive $zip): string
    {
        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (preg_match('/<sheet [^>]*name="Template"[^>]*r:id="([^"]+)"/', $workbook, $m)
            && preg_match('/<Relationship [^>]*Id="'.preg_quote($m[1], '/').'"[^>]*Target="([^"]+)"/', $rels, $t)) {
            return 'xl/'.ltrim($t[1], '/');
        }

        return 'xl/worksheets/sheet2.xml';
    }
}
