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
 * Halaman "Harga & Margin": tabel ala spreadsheet untuk menyunting harga
 * modal, harga jual, harga coret, fee afiliator, dan STOK langsung di
 * barisnya — dengan persentase profit margin (dari revenue) per produk.
 * Produk bervarian menampilkan baris variannya (harga & stok hidup di sana).
 * Perubahan stok dijalankan lewat StockService (gudang default) sehingga
 * kartu stok & riwayat pergerakan tetap konsisten.
 */
class PriceController extends Controller
{
    public function __construct(private readonly StockService $stock) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $brandId = $request->query('brand');
        $only = $request->query('tampil'); // margin-tipis | tanpa-modal

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
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.prices.index', [
            'products' => $products,
            'q' => $q,
            'brandId' => $brandId,
            'only' => $only,
            'brandOptions' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Simpan satu baris (dipanggil via fetch saat sel selesai disunting). */
    public function update(Request $request, Product $produk): JsonResponse
    {
        // ---- Baris VARIAN: harga jual/coret + stok milik varian itu. ----
        if ($request->filled('variant_id')) {
            $data = $request->validate([
                'variant_id' => ['required', 'integer'],
                'price' => ['required', 'numeric', 'min:0'],
                'compare_price' => ['nullable', 'numeric', 'min:0'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'stock' => ['nullable', 'integer', 'min:0'],
            ]);

            $variant = ProductVariant::where('product_id', $produk->id)->findOrFail((int) $data['variant_id']);

            // Modal per varian (kosong = memakai modal produk induk).
            if (array_key_exists('cost_price', $data)) {
                $variant->cost_price = $data['cost_price'] !== null && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null;
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
            $variant->save();

            if (array_key_exists('stock', $data) && $data['stock'] !== null) {
                $this->setStockTo($produk, $variant, (int) $data['stock']);
            }

            // Harga "mulai dari" di kartu katalog = varian termurah.
            $produk->update(['price' => $produk->variants()->where('is_active', true)->get()
                ->map(fn (ProductVariant $v) => $v->effectivePrice())->min() ?? $produk->price]);

            return response()->json($this->variantRow($variant->fresh(), $produk->fresh()));
        }

        // ---- Baris PRODUK. ----
        $isVariable = $produk->product_type === 'variable' || $produk->variants()->where('is_active', true)->exists();

        $data = $request->validate([
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Harga & stok produk bervarian diatur pada baris variannya.
            'price' => $isVariable ? ['prohibited'] : ['required', 'numeric', 'min:0'],
            'compare_price' => $isVariable ? ['prohibited'] : ['nullable', 'numeric', 'min:0'],
            'stock' => $isVariable ? ['prohibited'] : ['nullable', 'integer', 'min:0'],
        ]);

        if (array_key_exists('cost_price', $data)) {
            $produk->cost_price = $data['cost_price'] !== null && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null;
        }
        if (array_key_exists('affiliate_rate', $data)) {
            $produk->affiliate_rate = $data['affiliate_rate'] !== null && $data['affiliate_rate'] !== '' ? (float) $data['affiliate_rate'] : null;
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

        $produk->save();

        if (! $isVariable && array_key_exists('stock', $data) && $data['stock'] !== null) {
            $this->setStockTo($produk, null, (int) $data['stock']);
        }

        return response()->json($this->row($produk->fresh()));
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
            $this->stock->adjust($product, $variant, $delta, StockMovementType::Adjustment, note: 'Penyesuaian dari halaman Harga & Margin');
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['stock' => $e->getMessage()]);
        }
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
            'diskon_pct' => $variant->sale_price !== null && (float) $variant->price > 0 && (float) $variant->price > $jual
                ? round((1 - $jual / (float) $variant->price) * 100, 1)
                : null,
            'margin_pct' => $modalEfektif > 0 && $jual > 0 ? round(($jual - $modalEfektif) / $jual * 100, 2) : null,
        ];
    }
}
