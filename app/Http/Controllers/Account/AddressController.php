<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function index(): View
    {
        return view('account.addresses.index', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.form', [
            'address' => new CustomerAddress,
            'citiesByProvince' => IndahCargoRate::citiesByProvince(),
            'courierSearchEnabled' => app(RajaOngkirClient::class)->enabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->persist($request, new CustomerAddress(['user_id' => $request->user()->id]));

        return redirect()->route('account.addresses.index')->with('success', 'Alamat ditambahkan.');
    }

    public function edit(CustomerAddress $alamat): View
    {
        $this->authorizeAddress($alamat);

        return view('account.addresses.form', [
            'address' => $alamat,
            'citiesByProvince' => IndahCargoRate::citiesByProvince(),
            'courierSearchEnabled' => app(RajaOngkirClient::class)->enabled(),
        ]);
    }

    public function update(Request $request, CustomerAddress $alamat): RedirectResponse
    {
        $this->authorizeAddress($alamat);
        $this->persist($request, $alamat);

        return redirect()->route('account.addresses.index')->with('success', 'Alamat diperbarui.');
    }

    public function destroy(CustomerAddress $alamat): RedirectResponse
    {
        $this->authorizeAddress($alamat);
        $alamat->delete();

        return back()->with('success', 'Alamat dihapus.');
    }

    private function persist(Request $request, CustomerAddress $address): void
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($request) {
                $cities = IndahCargoRate::citiesByProvince()[$request->input('province')] ?? [];
                if (! in_array($value, $cities, true)) {
                    $fail('Kota/kabupaten harus dipilih dari daftar (sesuai jangkauan Indah Cargo).');
                }
            }],
            'district' => ['nullable', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            // ID kelurahan RajaOngkir dari kotak pencarian (dasar ongkir kurir reguler).
            'courier_destination_id' => ['nullable', 'integer', 'min:1'],
            'courier_destination_label' => ['nullable', 'string', 'max:255'],
            'address_line' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $address, $request) {
            $isDefault = (bool) ($data['is_default'] ?? false);
            if ($isDefault) {
                $request->user()->addresses()->update(['is_default' => false]);
            }
            $address->fill($data);
            $address->is_default = $isDefault || $request->user()->addresses()->count() === 0;
            $address->user_id = $request->user()->id;
            $address->save();
        });
    }

    private function authorizeAddress(CustomerAddress $address): void
    {
        abort_unless($address->user_id === auth()->id(), 403);
    }
}
