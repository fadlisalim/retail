<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::withCount('stocks')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.warehouses.index', compact('warehouses'));
    }

    public function create(): View
    {
        $warehouse = new Warehouse(['is_active' => true, 'is_default' => false]);

        return view('admin.warehouses.create', compact('warehouse'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $warehouse = Warehouse::create($data);
        $this->syncDefault($warehouse);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function edit(Warehouse $gudang): View
    {
        return view('admin.warehouses.edit', ['warehouse' => $gudang]);
    }

    public function update(Request $request, Warehouse $gudang): RedirectResponse
    {
        $gudang->update($this->validated($request, $gudang));
        $this->syncDefault($gudang);

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Gudang berhasil diperbarui.');
    }

    public function destroy(Warehouse $gudang): RedirectResponse
    {
        $gudang->delete();

        return redirect()->route('admin.warehouses.index')
            ->with('success', 'Gudang berhasil dihapus.');
    }

    /** Only one warehouse can be the default; clear the flag on the rest. */
    private function syncDefault(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            Warehouse::whereKeyNot($warehouse->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }
    }

    private function validated(Request $request, ?Warehouse $warehouse): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $data['is_default'] = $request->boolean('is_default');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
