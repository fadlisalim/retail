<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $products = Product::query()
            // Variable products are adjusted per variant, so the rows need them.
            ->with(['variants' => fn ($v) => $v->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
            ->whereIn('status', ['published', 'draft'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $types = StockMovementType::cases();

        return view('admin.stock.index', compact('products', 'q', 'types'));
    }

    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['add', 'subtract', 'set'])],
            'amount' => ['required', 'integer', 'min:0'],
            'type' => ['required', Rule::in(array_column(StockMovementType::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:1000'],
            // A variable product holds its stock per variant, so the row being
            // adjusted must be named explicitly.
            'variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
        ], [
            'amount.required' => 'Isi jumlahnya dulu.',
            'amount.integer' => 'Jumlah harus berupa angka.',
            'amount.min' => 'Jumlah tidak boleh negatif.',
        ]);

        $variant = ! empty($data['variant_id'])
            ? \App\Models\ProductVariant::find($data['variant_id'])
            : null;

        $amount = (int) $data['amount'];
        // "Set" compares against the row being changed: the variant's own stock
        // when one is chosen, otherwise the product-level stock.
        $current = (int) ($variant?->stock ?? $product->stock);
        $delta = match ($data['mode']) {
            'add' => $amount,
            'subtract' => -$amount,
            'set' => $amount - $current,
        };

        if ($delta === 0) {
            return back()->with('error', 'Stok tidak berubah (jumlah sama dengan stok saat ini).');
        }

        try {
            app(StockService::class)->adjust(
                $product,
                $variant,
                $delta,
                StockMovementType::from($data['type']),
                note: $data['note'] ?? null,
                userId: auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $variant
            ? 'Stok varian "'.$variant->name.'" kini '.$variant->fresh()->stock.'.'
            : 'Stok "'.$product->name.'" kini '.$product->fresh()->stock.'.');
    }
}
