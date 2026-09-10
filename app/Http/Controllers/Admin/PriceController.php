<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\WarehouseStock;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman "Edit Cepat Produk": tabel ala spreadsheet untuk menyunting harga
 * modal, harga jual, harga coret, fee afiliator, STOK, serta BERAT & DIMENSI
 * langsung di barisnya — dengan persentase profit margin (dari revenue) dan
 * berat volumetrik per baris. Produk bervarian menampilkan baris variannya
 * (harga, stok, berat & dimensi hidup di sana; kosong = mengikuti induk).
 *
 * Izin: price.manage boleh semua kolom; inventory.manage (admin gudang) hanya
 * stok, berat & dimensi — kolom harga ditolak server dan dinonaktifkan di UI.
 * Perubahan stok dijalankan lewat StockService (gudang default) sehingga
 * kartu stok & riwayat pergerakan tetap konsisten.
 */
class PriceController extends Controller
{
    /** Pembagi volumetrik standar kurir (cm³ → kg). */
    private const VOLUMETRIC_DIVISOR = 6000;

    private const SHIPPING_FIELDS = ['weight_grams', 'length_cm', 'width_cm', 'height_cm'];

    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand');
        $only = $request->query('tampil'); // margin-tipis | tanpa-modal | stok-habis | stok-menipis | tanpa-berat

        $products = Product::with([
            'brand:id,name',
            'variants' => fn ($v) => $v->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
        ])
            ->when($q !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%"),
            ))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($only === 'tanpa-modal', fn ($query) => $query->where(
                fn ($sub) => $sub->whereNull('cost_price')->orWhere('cost_price', '<=', 0),
            ))
            // Margin tipis butuh harga efektif, jadi disaring per baris di SQL:
            // sale_price aktif hanya bila lebih rendah dari price.
            ->when($only === 'margin-tipis', fn ($query) => $query
                ->whereNotNull('cost_price')->where('cost_price', '>', 0)
                ->whereRaw('COALESCE(CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price END, price) > 0')
                ->whereRaw('(COALESCE(CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price END, price) - cost_price)
                    < 0.2 * COALESCE(CASE WHEN sale_price IS NOT NULL AND sale_price < price THEN sale_price END, price)'))
            ->when($only === 'stok-habis', fn ($query) => $query->where(
                fn ($sub) => $sub->whereNull('stock')->orWhere('stock', '<=', 0),
            ))
            ->when($only === 'stok-menipis', fn ($query) => $query->where('stock', '>', 0)->whereColumn('stock', '<=', 'min_stock'))
            ->when($only === 'tanpa-berat', fn ($query) => $query->where(fn ($sub) => $sub
                ->where('weight_grams', '<=', 0)
                ->orWhere('length_cm', '<=', 0)->orWhere('width_cm', '<=', 0)->orWhere('height_cm', '<=', 0)))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.prices.index', [
            'products' => $products,
            'q' => $q,
            'brandId' => $brandId,
            'only' => $only,
            'brandOptions' => Brand::orderBy('name')->pluck('name', 'id'),
            'canPrice' => $request->user()->hasPermission('price.manage'),
            'divisor' => self::VOLUMETRIC_DIVISOR,
        ]);
    }

    /** Simpan satu baris (dipanggil via fetch saat sel selesai disunting). */
    public function update(Request $request, Product $produk): JsonResponse
    {
        $canPrice = $request->user()->hasPermission('price.manage');
        // Admin gudang tidak boleh menyentuh kolom harga — ditolak, bukan diabaikan diam-diam.
        $priceRule = fn (array $rules) => $canPrice ? $rules : ['prohibited'];

        $shippingRules = [
            'weight_grams' => ['nullable', 'integer', 'min:0', 'max:5000000'],
            'length_cm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'width_cm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
        ];

        // ---- Baris VARIAN: harga jual/coret, modal, stok, berat & dimensi milik varian itu. ----
        if ($request->filled('variant_id')) {
            $data = $request->validate([
                'variant_id' => ['required', 'integer'],
                'price' => $priceRule(['required', 'numeric', 'min:0']),
                'compare_price' => $priceRule(['nullable', 'numeric', 'min:0']),
                'cost_price' => $priceRule(['nullable', 'numeric', 'min:0']),
                'stock' => ['nullable', 'integer', 'min:0'],
            ] + $shippingRules);

            $variant = ProductVariant::where('product_id', $produk->id)->findOrFail((int) $data['variant_id']);

            if ($canPrice) {
                // Modal per varian (kosong = memakai modal produk induk).
                if (array_key_exists('cost_price', $data)) {
                    $variant->cost_price = $this->numberOrNull($data['cost_price']);
                }

                // Pemetaan sama dengan form produk: coret > jual → price+sale_price.
                $jual = (float) $data['price'];
                $coret = $data['compare_price'] ?? null;
                if ($coret !== null && $coret !== '' && (float) $coret > $jual) {
                    $variant->price = (float) $coret;
                    $variant->sale_price = $jual;
                } else {
                    $variant->price = $jual;
                    $variant->sale_price = null;
                }
            }

            // Berat & dimensi varian: kosong = mengikuti produk induk.
            foreach (self::SHIPPING_FIELDS as $field) {
                if (array_key_exists($field, $data)) {
                    $variant->{$field} = $this->numberOrNull($data[$field]);
                }
            }
            $variant->save();

            if (array_key_exists('stock', $data) && $data['stock'] !== null) {
                $this->setStockTo($produk, $variant, (int) $data['stock']);
            }

            if ($canPrice) {
                // Harga "mulai dari" di kartu katalog = varian termurah.
                $produk->update(['price' => $produk->variants()->where('is_active', true)->get()
                    ->map(fn (ProductVariant $v) => $v->effectivePrice())->min() ?? $produk->price]);
            }

            return response()->json($this->variantRow($variant->fresh(), $produk->fresh()));
        }

        // ---- Baris PRODUK. ----
        $isVariable = $produk->product_type === 'variable' || $produk->variants()->where('is_active', true)->exists();

        $data = $request->validate([
            'cost_price' => $priceRule(['nullable', 'numeric', 'min:0']),
            'affiliate_rate' => $priceRule(['nullable', 'numeric', 'min:0', 'max:100']),
            // Harga & stok produk bervarian diatur pada baris variannya.
            'price' => $isVariable ? ['prohibited'] : $priceRule(['required', 'numeric', 'min:0']),
            'compare_price' => $isVariable ? ['prohibited'] : $priceRule(['nullable', 'numeric', 'min:0']),
            'stock' => $isVariable ? ['prohibited'] : ['nullable', 'integer', 'min:0'],
        ] + $shippingRules);

        if ($canPrice) {
            if (array_key_exists('cost_price', $data)) {
                $produk->cost_price = $this->numberOrNull($data['cost_price']);
            }
            if (array_key_exists('affiliate_rate', $data)) {
                $produk->affiliate_rate = $this->numberOrNull($data['affiliate_rate']);
            }

            if (! $isVariable) {
                // Pemetaan yang sama dengan form produk: "Harga Coret" yang lebih
                // tinggi dari harga jual menjadi price + sale_price.
                $jual = (float) $data['price'];
                $coret = $data['compare_price'] ?? null;
                if ($coret !== null && $coret !== '' && (float) $coret > $jual) {
                    $produk->price = (float) $coret;
                    $produk->sale_price = $jual;
                } else {
                    $produk->price = $jual;
                    $produk->sale_price = null;
                }
            }
        }

        // Kolom produk tidak nullable: sel dikosongkan = 0.
        foreach (self::SHIPPING_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $produk->{$field} = $this->numberOrNull($data[$field]) ?? 0;
            }
        }

        $produk->save();

        if (! $isVariable && array_key_exists('stock', $data) && $data['stock'] !== null) {
            $this->setStockTo($produk, null, (int) $data['stock']);
        }

        return response()->json($this->row($produk->fresh()));
    }

    private function numberOrNull(mixed $value): ?float
    {
        return $value !== null && $value !== '' ? (float) $value : null;
    }

    /**
     * Set stok tersedia ke angka target lewat penyesuaian di gudang default —
     * kartu stok & riwayat pergerakan tercatat, bukan menimpa kolom cache.
     */
    private function setStockTo(Product $product, ?ProductVariant $variant, int $target): void
    {
        $current = (int) WarehouseStock::where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->sum('quantity_available');

        $delta = $target - $current;
        if ($delta === 0) {
            return;
        }

        try {
            $this->stock->adjust($product, $variant, $delta, StockMovementType::Adjustment,
                note: 'Penyesuaian dari halaman Edit Cepat Produk', userId: auth()->id());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['stock' => $e->getMessage()]);
        }
    }

    /** Berat volumetrik (gram) dari dimensi cm; 0 bila dimensi belum lengkap. */
    private function volumetricGrams(float $l, float $w, float $h): int
    {
        return ($l > 0 && $w > 0 && $h > 0) ? (int) round($l * $w * $h / self::VOLUMETRIC_DIVISOR * 1000) : 0;
    }

    /** Bentuk baris untuk respons JSON — dipakai front-end menyegarkan sel. */
    private function row(Product $product): array
    {
        $jual = $product->effectivePrice();
        $modal = (float) ($product->cost_price ?? 0);

        return [
            'id' => $product->id,
            'jual' => $jual,
            'coret' => $product->isOnSale() ? (float) $product->price : null,
            'modal' => $product->cost_price !== null ? (float) $product->cost_price : null,
            'fee' => $product->affiliate_rate !== null ? (float) $product->affiliate_rate : null,
            'stok' => (int) $product->stock,
            'min_stok' => (int) $product->min_stock,
            'berat' => (int) $product->weight_grams,
            'p' => (float) $product->length_cm,
            'l' => (float) $product->width_cm,
            't' => (float) $product->height_cm,
            'volumetrik' => $this->volumetricGrams((float) $product->length_cm, (float) $product->width_cm, (float) $product->height_cm),
            'diskon_pct' => $product->isOnSale() && (float) $product->price > 0
                ? round((1 - $jual / (float) $product->price) * 100, 1)
                : null,
            'margin_pct' => $modal > 0 && $jual > 0 ? round(($jual - $modal) / $jual * 100, 2) : null,
        ];
    }

    private function variantRow(ProductVariant $variant, Product $product): array
    {
        $jual = $variant->effectivePrice();
        // Margin varian dari modal varian; bila kosong, pakai modal produk induk.
        $modalEfektif = (float) ($variant->cost_price ?? $product->cost_price ?? 0);

        return [
            'id' => $variant->id,
            'jual' => $jual,
            'coret' => $variant->sale_price !== null && (float) $variant->price > $jual ? (float) $variant->price : null,
            'modal' => $variant->cost_price !== null ? (float) $variant->cost_price : null,
            'modal_induk' => $product->cost_price !== null ? (float) $product->cost_price : null,
            'fee' => null,
            'stok' => (int) $variant->stock,
            // Nilai milik varian (null = mengikuti induk) + nilai induk untuk placeholder.
            'berat' => $variant->weight_grams !== null ? (int) $variant->weight_grams : null,
            'p' => $variant->length_cm !== null ? (float) $variant->length_cm : null,
            'l' => $variant->width_cm !== null ? (float) $variant->width_cm : null,
            't' => $variant->height_cm !== null ? (float) $variant->height_cm : null,
            'berat_induk' => (int) $product->weight_grams,
            'p_induk' => (float) $product->length_cm,
            'l_induk' => (float) $product->width_cm,
            't_induk' => (float) $product->height_cm,
            'berat_efektif' => $variant->weightGrams(),
            'volumetrik' => $this->volumetricGrams($variant->lengthCm(), $variant->widthCm(), $variant->heightCm()),
            'diskon_pct' => $variant->sale_price !== null && (float) $variant->price > 0 && (float) $variant->price > $jual
                ? round((1 - $jual / (float) $variant->price) * 100, 1)
                : null,
            'margin_pct' => $modalEfektif > 0 && $jual > 0 ? round(($jual - $modalEfektif) / $jual * 100, 2) : null,
        ];
    }
}
