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
            'delta' => ['required', 'integer', 'not_in:0'],
            'type' => ['required', Rule::in(array_column(StockMovementType::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(StockService::class)->adjust(
                $product,
                null,
                (int) $data['delta'],
                StockMovementType::from($data['type']),
                note: $data['note'] ?? null,
                userId: auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stok "'.$product->name.'" berhasil disesuaikan.');
    }
}
