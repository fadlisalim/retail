<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CouponController extends Controller
{
    public function index(): View
    {
        $coupons = Coupon::orderByDesc('id')->paginate(20);

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create(): View
    {
        $coupon = new Coupon(['type' => 'percent', 'is_active' => true, 'is_combinable' => false]);

        return view('admin.coupons.create', compact('coupon'));
    }

    public function store(Request $request): RedirectResponse
    {
        Coupon::create($this->validated($request, null));

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Voucher berhasil ditambahkan.');
    }

    public function edit(Coupon $kupon): View
    {
        return view('admin.coupons.edit', ['coupon' => $kupon]);
    }

    public function update(Request $request, Coupon $kupon): RedirectResponse
    {
        $kupon->update($this->validated($request, $kupon));

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Voucher berhasil diperbarui.');
    }

    public function destroy(Coupon $kupon): RedirectResponse
    {
        $kupon->delete();

        return redirect()->route('admin.coupons.index')
            ->with('success', 'Voucher berhasil dihapus.');
    }

    private function validated(Request $request, ?Coupon $coupon): array
    {
        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code'))),
        ]);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(['percent', 'fixed', 'free_shipping'])],
            'value' => ['required', 'numeric', 'min:0'],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:0'],
            'is_combinable' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $data['value'] = $data['value'] ?? 0;
        $data['min_subtotal'] = $data['min_subtotal'] ?? 0;
        $data['is_combinable'] = $request->boolean('is_combinable');
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
