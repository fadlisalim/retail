<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use App\Services\WatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CRUD for a product's variants (each with its own price, stock, and image) —
 * the Tokopedia/Shopee-style variant picker. Kept separate from the main form.
 */
class ProductVariantController extends Controller
{
    public function store(Request $request, Product $produk, WatermarkService $watermark): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $variant = $produk->variants()->create([
            'sku' => $this->generateSku($produk, $data['name']),
            'name' => $data['name'],
            'option_values' => ['Varian' => $data['name']],
            'price' => $data['price'],
            'sale_price' => $data['sale_price'] ?? null,
            'weight_grams' => $data['weight_grams'] ?? null,
            'length_cm' => $data['length_cm'] ?? null,
            'width_cm' => $data['width_cm'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
            'is_active' => true,
            'sort_order' => (int) ($produk->variants()->max('sort_order') ?? 0) + 1,
            'image_path' => $request->hasFile('image')
                ? ($watermark->apply($p = $request->file('image')->store('products', 'public')) ?? $p)
                : null,
        ]);

        if (! empty($data['stock'])) {
            app(StockService::class)->adjust($produk, $variant, (int) $data['stock'], StockMovementType::Purchase, note: 'Stok awal varian', userId: auth()->id());
        }

        // A product with variants is a variable product.
        if ($produk->product_type !== 'variable') {
            $produk->update(['product_type' => 'variable']);
        }

        return back()->with('success', 'Varian "'.$variant->name.'" ditambahkan.');
    }

    public function update(Request $request, ProductVariant $varian, WatermarkService $watermark): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $attrs = [
            'name' => $data['name'],
            'option_values' => ['Varian' => $data['name']],
            'price' => $data['price'],
            'sale_price' => $data['sale_price'] ?? null,
            'weight_grams' => $data['weight_grams'] ?? null,
            'length_cm' => $data['length_cm'] ?? null,
            'width_cm' => $data['width_cm'] ?? null,
            'height_cm' => $data['height_cm'] ?? null,
        ];
        if ($request->hasFile('image')) {
            if ($varian->image_path) {
                Storage::disk('public')->delete($varian->image_path);
            }
            $stored = $request->file('image')->store('products', 'public');
            $attrs['image_path'] = $watermark->apply($stored) ?? $stored;
        }
        $varian->update($attrs);

        // Set stock to the given total via the ledger.
        $delta = (int) $data['stock'] - (int) $varian->stock;
        if ($delta !== 0) {
            app(StockService::class)->adjust($varian->product, $varian, $delta, StockMovementType::Adjustment, note: 'Set stok varian', userId: auth()->id());
        }

        return back()->with('success', 'Varian "'.$varian->name.'" diperbarui.');
    }

    public function destroy(ProductVariant $varian): RedirectResponse
    {
        if ($varian->image_path) {
            Storage::disk('public')->delete($varian->image_path);
        }
        $varian->delete();

        return back()->with('success', 'Varian dihapus.');
    }

    private function generateSku(Product $product, string $name): string
    {
        $base = Str::upper(Str::slug(($product->sku ?: 'VAR').'-'.Str::limit($name, 10, '')));
        $base = preg_replace('/[^A-Z0-9]+/', '-', $base) ?: 'VAR';

        do {
            $sku = trim($base, '-').'-'.Str::upper(Str::random(3));
        } while (ProductVariant::where('sku', $sku)->exists());

        return $sku;
    }
}
