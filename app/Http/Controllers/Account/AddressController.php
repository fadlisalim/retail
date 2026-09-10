<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\IndahCargoRate;
use App\Models\Region;
use App\Services\Shipping\CourierRegions;
use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AddressController extends Controller
{
    public function __construct(
        private readonly RajaOngkirClient $courier,
        private readonly CourierRegions $regions,
    ) {}

    public function index(): View
    {
        return view('account.addresses.index', [
            'addresses' => auth()->user()->addresses()->orderByDesc('is_default')->get(),
        ]);
    }

    public function create(): View
    {
        return view('account.addresses.form', $this->formData(new CustomerAddress));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->persist($request, new CustomerAddress(['user_id' => $request->user()->id]));

        return redirect()->route('account.addresses.index')->with('success', 'Alamat ditambahkan.');
    }

    public function edit(CustomerAddress $alamat): View
    {
        $this->authorizeAddress($alamat);

        return view('account.addresses.form', $this->formData($alamat));
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

    /**
     * Data form. Saat ongkir kurir aktif, wilayah dipilih bertingkat dari tabel
     * regions (sinkron RajaOngkir); kalau tidak, provinsi/kota dari daftar Indah.
     */
    private function formData(CustomerAddress $address): array
    {
        $enabled = $this->courier->enabled();

        // Prefill dropdown bertingkat dari region kelurahan yang tersimpan.
        $selected = ['province' => null, 'city' => null, 'district' => null, 'subdistrict' => null];
        if ($enabled && $address->region_id && ($leaf = Region::find($address->region_id))) {
            foreach ($leaf->chain() as $node) {
                $selected[$node->type] = $node->id;
            }
        }

        return [
            'address' => $address,
            'citiesByProvince' => $enabled ? [] : IndahCargoRate::citiesByProvince(),
            'courierSearchEnabled' => $enabled,
            'provinces' => $enabled ? $this->regions->provinces()->map(fn (Region $r) => ['id' => $r->id, 'name' => $r->name])->values()->all() : [],
            'selectedRegions' => $selected,
        ];
    }

    private function persist(Request $request, CustomerAddress $address): void
    {
        $enabled = $this->courier->enabled();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address_line' => ['required', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ] + ($enabled ? [
            // Wilayah dipilih bertingkat dari tabel regions — nama & ID RajaOngkir
            // diturunkan dari sini, bukan dari teks bebas.
            'province_id' => ['required', 'integer', 'exists:regions,id'],
            'city_id' => ['required', 'integer', 'exists:regions,id'],
            'district_id' => ['required', 'integer', 'exists:regions,id'],
            'subdistrict_id' => ['required', 'integer', 'exists:regions,id'],
        ] : [
            'province' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($request) {
                $cities = IndahCargoRate::citiesByProvince()[$request->input('province')] ?? [];
                if (! in_array($value, $cities, true)) {
                    $fail('Kota/kabupaten harus dipilih dari daftar (sesuai jangkauan Indah Cargo).');
                }
            }],
            'district' => ['nullable', 'string', 'max:100'],
            'subdistrict' => ['nullable', 'string', 'max:100'],
        ]), [
            'province_id.required' => 'Pilih provinsi.',
            'city_id.required' => 'Pilih kota/kabupaten.',
            'district_id.required' => 'Pilih kecamatan.',
            'subdistrict_id.required' => 'Pilih kelurahan/desa — dasar perhitungan ongkir kurir.',
        ]);

        if ($enabled) {
            $data = array_merge($data, $this->regionFields($data));
        }

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

    /** Turunkan nama wilayah, kode pos, dan ID tujuan kurir dari rantai region yang dipilih. */
    private function regionFields(array $data): array
    {
        $sub = Region::with('parent.parent.parent')->find($data['subdistrict_id']);
        $district = $sub?->parent;
        $city = $district?->parent;
        $province = $city?->parent;

        $consistent = $sub && $sub->type === 'subdistrict'
            && $district && $district->id === (int) $data['district_id']
            && $city && $city->id === (int) $data['city_id']
            && $province && $province->id === (int) $data['province_id'];

        if (! $consistent) {
            throw ValidationException::withMessages(['subdistrict_id' => 'Pilihan wilayah tidak konsisten — pilih ulang mulai dari provinsi.']);
        }

        return [
            'province' => $province->name,
            'city' => $city->name,
            'district' => $district->name,
            'subdistrict' => $sub->name,
            'postal_code' => ($data['postal_code'] ?? null) ?: $sub->postal_code,
            'region_id' => $sub->id,
            'courier_destination_id' => (int) $sub->code,
            'courier_destination_label' => $sub->courierLabel(),
        ];
    }

    private function authorizeAddress(CustomerAddress $address): void
    {
        abort_unless($address->user_id === auth()->id(), 403);
    }
}
