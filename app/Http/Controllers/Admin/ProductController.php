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

        $products = Product::with(['brand', 'category'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', compact('products', 'q', 'status'));
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

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $produk): View
    {
        return view('admin.products.edit', $this->formOptions() + ['product' => $produk]);
    }

    public function update(Request $request, Product $produk): RedirectResponse
    {
        $produk->update($this->validated($request, $produk));

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $produk): RedirectResponse
    {
        $produk->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
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
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'new_brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'product_type' => ['required', Rule::in(['simple', 'variable', 'bundle', 'service'])],
            'condition' => ['required', Rule::in(['new', 'open_box', 'display_unit', 'used'])],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],           // "Harga Jual"
            'compare_price' => ['nullable', 'numeric', 'min:0'],   // "Harga Coret" (higher, optional)
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'affiliate_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'price_includes_tax' => ['boolean'],
            'is_taxable' => ['boolean'],
            'unit' => ['nullable', 'string', 'max:30'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'package_count' => ['nullable', 'integer', 'min:1'],
            'can_combine_package' => ['boolean'],
            'requires_freight' => ['boolean'],
            'pickup_only' => ['boolean'],
            'warranty' => ['nullable', 'string', 'max:255'],
            'estimated_processing' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'is_promo' => ['boolean'],
            'is_clearance' => ['boolean'],
            'is_purchasable' => ['boolean'],
            'requires_quotation' => ['boolean'],
            'min_purchase' => ['nullable', 'integer', 'min:1'],
            'max_purchase' => ['nullable', 'integer', 'min:1'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'initial_stock' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ([
            'price_includes_tax', 'is_taxable', 'can_combine_package', 'requires_freight',
            'pickup_only', 'is_featured', 'is_new', 'is_promo', 'is_clearance',
            'is_purchasable', 'requires_quotation',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        // Coalesce non-nullable columns so a blank field never writes NULL.
        $data['unit'] = $data['unit'] ?: 'pcs';
        $data['min_stock'] = (int) ($data['min_stock'] ?? 0);
        $data['weight_grams'] = (int) ($data['weight_grams'] ?? 0);
        $data['length_cm'] = $data['length_cm'] ?? 0;
        $data['width_cm'] = $data['width_cm'] ?? 0;
        $data['height_cm'] = $data['height_cm'] ?? 0;
        $data['package_count'] = (int) ($data['package_count'] ?? 1);
        $data['min_purchase'] = (int) ($data['min_purchase'] ?? 1);

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
