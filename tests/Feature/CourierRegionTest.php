<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\Region;
use App\Services\Shipping\CourierRegions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Wilayah RajaOngkir di tabel regions: diambil sekali per induk lalu permanen,
 * endpoint dropdown bertingkat, dan form alamat menyimpan nama/kode pos/ID
 * tujuan kurir dari rantai wilayah yang dipilih.
 */
class CourierRegionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.rajaongkir.enabled' => true,
            'services.rajaongkir.api_key' => 'test-key',
            'services.rajaongkir.origin_id' => 4853,
            'services.rajaongkir.base_url' => 'https://rajaongkir.komerce.id/api/v1',
        ]);

        $ok = fn (array $data) => Http::response(['meta' => ['message' => 'Success', 'code' => 200, 'status' => 'success'], 'data' => $data]);
        Http::fake([
            'rajaongkir.komerce.id/api/v1/destination/province' => $ok([['id' => 9, 'name' => 'JAWA BARAT'], ['id' => 32, 'name' => 'MALUKU UTARA'], ['id' => 6, 'name' => 'DKI JAKARTA']]),
            'rajaongkir.komerce.id/api/v1/destination/city/9' => $ok([['id' => 23, 'name' => 'KOTA BANDUNG'], ['id' => 22, 'name' => 'KAB. BANDUNG BARAT']]),
            'rajaongkir.komerce.id/api/v1/destination/city/32' => $ok([['id' => 456, 'name' => 'KOTA TERNATE']]),
            'rajaongkir.komerce.id/api/v1/destination/district/23' => $ok([['id' => 300, 'name' => 'COBLONG'], ['id' => 301, 'name' => 'ARCAMANIK']]),
            'rajaongkir.komerce.id/api/v1/destination/district/22' => $ok([['id' => 320, 'name' => 'LEMBANG']]),
            'rajaongkir.komerce.id/api/v1/destination/sub-district/300' => $ok([['id' => 17473, 'name' => 'DAGO', 'zip_code' => '40135'], ['id' => 17474, 'name' => 'LEBAK GEDE', 'zip_code' => '40132']]),
            'rajaongkir.komerce.id/api/v1/destination/city/6' => Http::response(['meta' => ['message' => 'Server error', 'code' => 500]], 500),
            '*' => Http::response(['meta' => ['message' => 'Not found', 'code' => 404]], 404),
        ]);
    }

    /** Rantai Jawa Barat → Kota Bandung → Coblong → Dago lewat service (memicu 4 request). */
    private function chain(): array
    {
        $regions = app(CourierRegions::class);
        $province = $regions->provinces()->firstWhere('name', 'Jawa Barat');
        $city = $regions->children($province)->firstWhere('name', 'Kota Bandung');
        $district = $regions->children($city)->firstWhere('name', 'Coblong');
        $sub = $regions->children($district)->firstWhere('name', 'Dago');

        return [$province, $city, $district, $sub];
    }

    public function test_provinces_and_children_are_fetched_once_then_served_from_the_database(): void
    {
        $regions = app(CourierRegions::class);

        $provinces = $regions->provinces();
        $regions->provinces();
        $this->assertSame(['DKI Jakarta', 'Jawa Barat', 'Maluku Utara'], $provinces->pluck('name')->all());
        $this->assertSame('9', Region::where('name', 'Jawa Barat')->value('code'));

        [$province, $city, $district, $sub] = $this->chain();
        $regions->children($province); // sudah tersinkron → tidak ada request lagi
        $this->assertSame(['Kab. Bandung Barat', 'Kota Bandung'], $province->children()->orderBy('name')->pluck('name')->all());
        $this->assertNotNull($province->fresh()->children_synced_at);
        $this->assertSame('40135', $sub->postal_code);
        $this->assertSame('17473', $sub->code);
        $this->assertSame('Dago, Coblong, Kota Bandung, Jawa Barat, 40135', $sub->courierLabel());

        Http::assertSentCount(4);
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/destination/sub-district/300') && $r->hasHeader('key', 'test-key'));
    }

    public function test_api_failure_leaves_the_parent_unsynced_so_it_is_retried_later(): void
    {
        $regions = app(CourierRegions::class);
        $jakarta = $regions->provinces()->firstWhere('name', 'DKI Jakarta');

        $this->assertCount(0, $regions->children($jakarta));
        $this->assertNull($jakarta->fresh()->children_synced_at);

        $regions->children($jakarta); // dicoba lagi, bukan dianggap kosong permanen
        Http::assertSentCount(3); // provinsi + 2× kota Jakarta
    }

    public function test_regions_endpoint_serves_cascading_dropdowns(): void
    {
        $this->getJson(route('shipping.regions'))->assertUnauthorized();

        $customer = $this->customer();
        $provinces = $this->actingAs($customer)->getJson(route('shipping.regions'))->assertOk()->json();
        $jabar = collect($provinces)->firstWhere('name', 'Jawa Barat');
        $this->assertNotNull($jabar);

        $cities = $this->actingAs($customer)->getJson(route('shipping.regions', ['parent' => $jabar['id']]))->assertOk()->json();
        $this->assertSame(['Kab. Bandung Barat', 'Kota Bandung'], array_column($cities, 'name'));

        $bandung = collect($cities)->firstWhere('name', 'Kota Bandung');
        $districts = $this->actingAs($customer)->getJson(route('shipping.regions', ['parent' => $bandung['id']]))->assertOk()->json();
        $coblong = collect($districts)->firstWhere('name', 'Coblong');
        $subs = $this->actingAs($customer)->getJson(route('shipping.regions', ['parent' => $coblong['id']]))->assertOk()->json();
        $this->assertSame([['name' => 'Dago', 'postal_code' => '40135', 'code' => '17473']], array_map(fn ($s) => ['name' => $s['name'], 'postal_code' => $s['postal_code'], 'code' => $s['code']], array_filter($subs, fn ($s) => $s['name'] === 'Dago')));
    }

    public function test_address_form_renders_cascading_selects_and_saves_names_from_the_chain(): void
    {
        $customer = $this->customer();
        [$province, $city, $district, $sub] = $this->chain();

        $this->actingAs($customer)->get(route('account.addresses.create'))
            ->assertOk()->assertSee('name="province_id"', false)->assertSee('Jawa Barat')->assertSee('Cari cepat kelurahan');

        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Rumah', 'recipient_name' => 'Tester', 'phone' => '0811', 'address_line' => 'Jl. Dago 1',
            'province_id' => $province->id, 'city_id' => $city->id, 'district_id' => $district->id, 'subdistrict_id' => $sub->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $address = CustomerAddress::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame('Jawa Barat', $address->province);
        $this->assertSame('Kota Bandung', $address->city);
        $this->assertSame('Coblong', $address->district);
        $this->assertSame('Dago', $address->subdistrict);
        $this->assertSame('40135', $address->postal_code);
        $this->assertSame($sub->id, $address->region_id);
        $this->assertSame(17473, (int) $address->courier_destination_id);
        $this->assertSame('Dago, Coblong, Kota Bandung, Jawa Barat, 40135', $address->courier_destination_label);

        // Form ubah: dropdown terisi dari rantai region alamat.
        $this->actingAs($customer)->get(route('account.addresses.edit', $address))
            ->assertOk()->assertSee('subdistrict: '.$sub->id, false);

        // Rantai tidak konsisten (kelurahan bukan anak kecamatan yang dipilih) → ditolak.
        $other = Region::where('name', 'Arcamanik')->firstOrFail();
        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Kantor', 'recipient_name' => 'Tester', 'phone' => '0811', 'address_line' => 'Jl. Uji 2',
            'province_id' => $province->id, 'city_id' => $city->id, 'district_id' => $other->id, 'subdistrict_id' => $sub->id,
        ])->assertSessionHasErrors('subdistrict_id');

        // Tanpa kelurahan → wajib.
        $this->actingAs($customer)->post(route('account.addresses.store'), [
            'label' => 'Kantor', 'recipient_name' => 'Tester', 'phone' => '0811', 'address_line' => 'Jl. Uji 2',
            'province_id' => $province->id, 'city_id' => $city->id, 'district_id' => $district->id,
        ])->assertSessionHasErrors('subdistrict_id');
    }

    public function test_sync_command_fills_provinces_and_cities_and_can_batch_districts(): void
    {
        $this->artisan('ongkir:sync-wilayah', ['--kecamatan' => 1])->assertSuccessful();

        $this->assertSame(3, Region::where('type', 'province')->count());
        $this->assertSame(3, Region::where('type', 'city')->count()); // Jakarta gagal (500) → 0 kota, tidak ditandai
        $this->assertNull(Region::where('name', 'DKI Jakarta')->first()->children_synced_at);
        $this->assertSame(1, Region::where('type', 'city')->whereNotNull('children_synced_at')->count());
    }
}
