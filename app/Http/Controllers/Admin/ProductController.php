<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $categoryId = $request->query('category');
        $brandId = $request->query('brand');
        $media = $request->query('media');

        $products = Product::with(['brand', 'category'])
            ->withCount(['images', 'documents', 'videos'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($categoryId, function ($query) use ($categoryId) {
                // Match the picked category (and its whole subtree) via primary or
                // additional categories — so a parent lists sub-category products too.
                if ($cat = Category::find($categoryId)) {
                    $ids = $cat->descendantIds();
                    $query->where(function ($sub) use ($ids) {
                        $sub->whereIn('category_id', $ids)
                            ->orWhereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids));
                    });
                }
            })
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($media, fn ($query) => $this->applyMediaFilter($query, $media))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'q' => $q,
            'status' => $status,
            'categoryId' => $categoryId,
            'brandId' => $brandId,
            'media' => $media,
            'mediaOptions' => self::MEDIA_FILTERS,
            'categoryOptions' => $this->categoryOptions(),
            'brandOptions' => Brand::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Pilihan filter kelengkapan media pada daftar produk. */
    public const MEDIA_FILTERS = [
        'no-image' => 'Belum ada foto',
        'no-document' => 'Belum ada dokumen',
        'no-video' => 'Belum ada video',
        'incomplete' => 'Belum lengkap (salah satu kosong)',
    ];

    /**
     * "Belum ada foto" berarti benar-benar tidak punya gambar: tanpa galeri DAN
     * tanpa foto utama — produk yang punya galeri tapi belum dipilih foto
     * utamanya tetap dianggap punya foto (thumbnail-nya memang belum muncul,
     * tetapi materinya sudah ada dan tinggal ditandai di halaman Edit).
     */
    private function applyMediaFilter($query, string $media): void
    {
        $withoutImage = fn ($sub) => $sub->whereDoesntHave('images')
            ->where(fn ($q) => $q->whereNull('main_image_path')->orWhere('main_image_path', ''));

        match ($media) {
            'no-image' => $withoutImage($query),
            'no-document' => $query->whereDoesntHave('documents'),
            'no-video' => $query->whereDoesntHave('videos'),
            'incomplete' => $query->where(fn ($sub) => $sub
                ->where($withoutImage)
                ->orWhereDoesntHave('documents')
                ->orWhereDoesntHave('videos')),
            default => null,
        };
    }

    public function create(): View
    {
        $product = new Product([
            'product_type' => 'simple',
            'condition' => 'new',
            'status' => 'draft',
            'unit' => 'pcs',
            'min_stock' => 0,
            'weight_grams' => 1000,
            'package_count' => 1,
            'min_purchase' => 1,
            'is_taxable' => true,
            'is_purchasable' => true,
            'can_combine_package' => true,
        ]);

        return view('admin.products.create', $this->formOptions() + ['product' => $product]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $initialStock = (int) $request->integer('initial_stock');

        $product = Product::create($data);
        $this->syncCategories($request, $product);

        if ($initialStock > 0) {
            app(StockService::class)->adjust(
                $product,
                null,
                $initialStock,
                StockMovementType::Purchase,
                note: 'Stok awal',
                userId: auth()->id(),
            );
        }

        // Stay on the product (its edit page) so images/media can be added next.
        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Produk berhasil disimpan. Silakan tambahkan gambar & media di bawah.');
    }

    public function edit(Product $produk): View
    {
        return view('admin.products.edit', $this->formOptions() + ['product' => $produk]);
    }

    public function update(Request $request, Product $produk): RedirectResponse
    {
        $produk->update($this->validated($request, $produk));
        $this->syncCategories($request, $produk);

        // Stay on the product after saving.
        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Sync the product's category pivot: the primary category_id plus any extra
     * categories chosen on the form (deduped). Keeps the pivot authoritative so
     * the product shows in every assigned category (and their parents).
     */
    private function syncCategories(Request $request, Product $product): void
    {
        $extra = array_map('intval', (array) $request->input('categories', []));
        $all = array_values(array_unique(array_filter(
            array_merge([(int) $product->category_id], $extra),
        )));

        $product->categories()->sync($all);
    }

    public function destroy(Product $produk): RedirectResponse
    {
        $produk->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    /**
     * Inline "fast edit" from the product list: price, stock, affiliate commission
     * and status — without opening the full form.
     */
    public function quickUpdate(Request $request, Product $produk, StockService $stock): RedirectResponse
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],           // Harga Jual
            'compare_price' => ['nullable', 'numeric', 'min:0'],   // Harga Coret
            'affiliate_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'stock' => ['nullable', 'integer', 'min:0'],
        ]);

        // Price model: "Harga Jual" + optional higher "Harga Coret" (same mapping as the full form).
        $jual = (float) $data['price'];
        $coret = $data['compare_price'] ?? null;
        if ($coret !== null && (float) $coret > $jual) {
            $produk->price = (float) $coret;
            $produk->sale_price = $jual;
        } else {
            $produk->price = $jual;
            $produk->sale_price = null;
        }

        $rate = $data['affiliate_rate'] ?? null;
        $produk->affiliate_rate = ($rate === null || $rate === '') ? null : (float) $rate;

        $produk->status = $data['status'];
        if ($data['status'] === 'published' && ! $produk->published_at) {
            $produk->published_at = now();
        }
        $produk->save();

        // Stock is editable only for non-variable products (variable stock lives per variant).
        if ($produk->product_type !== 'variable' && $request->filled('stock')) {
            $delta = (int) $data['stock'] - (int) $produk->stock;
            if ($delta !== 0) {
                $stock->adjust($produk, null, $delta, StockMovementType::Adjustment, note: 'Edit cepat', userId: auth()->id());
            }
        }

        return back()->with('success', 'Produk "'.$produk->name.'" diperbarui.');
    }

    /** Ubah status beberapa produk sekaligus dari daftar (pilih lalu terapkan). */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
        ]);

        $products = Product::whereIn('id', $data['ids'])->get();

        foreach ($products as $product) {
            $product->status = $data['status'];
            // Tanggal terbit hanya diisi sekali, agar urutan "produk baru" tidak
            // ikut teracak saat produk lama diterbitkan ulang.
            if ($data['status'] === 'published' && ! $product->published_at) {
                $product->published_at = now();
            }
            $product->save();
        }

        $label = ['draft' => 'Draft', 'published' => 'Terbit', 'archived' => 'Arsip'][$data['status']];

        return back()->with('success', $products->count().' produk diubah ke status '.$label.'.');
    }

    /** Build a unique SKU from the product name (fallback when the field is blank). */
    private function generateSku(string $name): string
    {
        $base = Str::upper(Str::slug(Str::of($name)->limit(12, '')));
        $base = preg_replace('/[^A-Z0-9]+/', '-', $base) ?: 'PRD';

        do {
            $sku = trim($base, '-').'-'.Str::upper(Str::random(4));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }

    /** Category + brand option lists shared by the create/edit forms. */
    private function formOptions(): array
    {
        return [
            'categories' => $this->categoryOptions(),
            'brands' => Brand::orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    /**
     * Hierarchical, tree-ordered category options so parents and their children
     * are distinguishable, e.g. "Inverter" then "Inverter › Hybrid".
     */
    private function categoryOptions(): array
    {
        $byParent = Category::orderBy('sort_order')->orderBy('name')->get()->groupBy('parent_id');

        $options = [];
        $walk = function ($parentId, string $prefix) use (&$walk, $byParent, &$options): void {
            foreach ($byParent->get($parentId ?? '', collect()) as $cat) {
                $options[$cat->id] = $prefix.$cat->name;
                $walk($cat->id, $prefix.$cat->name.' › ');
            }
        };
        $walk(null, '');

        return $options;
    }

    private function validated(Request $request, ?Product $product): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product?->id)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'new_brand' => ['nullable', 'string', 'max:255'],
            'spec_key' => ['nullable', 'array'],
            'spec_key.*' => ['nullable', 'string', 'max:255'],
            'spec_value' => ['nullable', 'array'],
            'spec_value.*' => ['nullable', 'string', 'max:1000'],
            'model' => ['nullable', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['simple', 'variable', 'bundle', 'service'])],
            'condition' => ['required', Rule::in(['new', 'new_minor_defect', 'new_project_surplus', 'open_box', 'display_unit', 'used'])],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],           // "Harga Jual"
            'compare_price' => ['nullable', 'numeric', 'min:0'],   // "Harga Coret" (higher, optional)
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['nullable', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'warranty' => ['nullable', 'string', 'max:255'],
            'estimated_processing' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'is_promo' => ['boolean'],
            'is_clearance' => ['boolean'],
            'badge_text' => ['nullable', 'string', 'max:60'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        // Badges are still on the form.
        foreach (['is_featured', 'is_new', 'is_promo', 'is_clearance'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // Extra categories are synced to the pivot separately (not a column).
        unset($data['categories']);

        // The following toggles were removed from the form — apply fixed policy
        // (all products purchasable, no RFQ) and preserve other flags on edit.
        $data['is_purchasable'] = true;
        $data['requires_quotation'] = false;
        $data['is_taxable'] = $product?->is_taxable ?? true;
        $data['price_includes_tax'] = $product?->price_includes_tax ?? true;
        $data['can_combine_package'] = $product?->can_combine_package ?? true;
        $data['requires_freight'] = $product?->requires_freight ?? false;
        $data['pickup_only'] = $product?->pickup_only ?? false;
        $data['min_purchase'] = $product?->min_purchase ?? 1;
        $data['max_purchase'] = $product?->max_purchase ?? null;

        // Store an empty custom tag as NULL so no blank badge renders.
        $data['badge_text'] = trim((string) ($data['badge_text'] ?? '')) ?: null;

        // Coalesce non-nullable columns so a blank field never writes NULL.
        $data['unit'] = $data['unit'] ?: 'pcs';
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);
        $data['weight_grams'] = (int) ($data['weight_grams'] ?? 0);
        $data['length_cm'] = $data['length_cm'] ?? 0;
        $data['width_cm'] = $data['width_cm'] ?? 0;
        $data['height_cm'] = $data['height_cm'] ?? 0;
        $data['package_count'] = (int) ($data['package_count'] ?? 1);

        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        // Price model: user enters selling price ("Harga Jual") + optional higher
        // "Harga Coret". Map to columns: coret -> price (struck), jual -> sale_price.
        $jual = (float) $data['price'];
        $coret = $data['compare_price'] ?? null;
        if ($coret !== null && (float) $coret > $jual) {
            $data['price'] = (float) $coret;
            $data['sale_price'] = $jual;
        } else {
            $data['price'] = $jual;
            $data['sale_price'] = null;
        }
        unset($data['compare_price']);

        // Auto-generate a SKU when left blank.
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSku($data['name']);
        }

        // Build the specifications HTML table from the atribut/nilai rows.
        $keys = $request->input('spec_key', []);
        $values = $request->input('spec_value', []);
        $rows = [];
        foreach ($keys as $i => $key) {
            $key = trim((string) $key);
            $val = trim((string) ($values[$i] ?? ''));
            if ($key === '' && $val === '') {
                continue;
            }
            $rows[] = '<tr><th>'.e($key).'</th><td>'.e($val).'</td></tr>';
        }
        $data['specifications'] = $rows ? '<table><tbody>'.implode('', $rows).'</tbody></table>' : null;
        unset($data['spec_key'], $data['spec_value']);

        // Inline "new brand": create (or reuse) it and assign, overriding the select.
        if ($request->filled('new_brand')) {
            $name = trim($request->input('new_brand'));
            $brand = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
            $data['brand_id'] = $brand->id;
        }
        unset($data['new_brand'], $data['initial_stock']);

        return $data;
    }
}
