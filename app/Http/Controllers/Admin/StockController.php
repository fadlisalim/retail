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
            'mode' => ['required', Rule::in(['add', 'subtract', 'set'])],
            'amount' => ['required', 'integer', 'min:0'],
            'type' => ['required', Rule::in(array_column(StockMovementType::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'amount.required' => 'Isi jumlahnya dulu.',
            'amount.integer' => 'Jumlah harus berupa angka.',
            'amount.min' => 'Jumlah tidak boleh negatif.',
        ]);

        $amount = (int) $data['amount'];
        $delta = match ($data['mode']) {
            'add' => $amount,
            'subtract' => -$amount,
            'set' => $amount - (int) $product->stock,
        };

        if ($delta === 0) {
            return back()->with('error', 'Stok tidak berubah (jumlah sama dengan stok saat ini).');
        }

        try {
            app(StockService::class)->adjust(
                $product,
                null,
                $delta,
                StockMovementType::from($data['type']),
                note: $data['note'] ?? null,
                userId: auth()->id(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Stok "'.$product->name.'" kini '.$product->fresh()->stock.'.');
    }
}
