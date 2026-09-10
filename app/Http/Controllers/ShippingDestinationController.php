<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\Shipping\CourierRegions;
use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data wilayah untuk form alamat: dropdown bertingkat dari tabel regions
 * (disinkron bertahap dari RajaOngkir) dan autocomplete kelurahan.
 */
class ShippingDestinationController extends Controller
{
    /** Autocomplete kelurahan/kecamatan (pencarian bebas ke RajaOngkir, di-cache). */
    public function search(Request $request, RajaOngkirClient $client): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:100']]);

        if (! $client->enabled()) {
            return response()->json([]);
        }

        return response()->json($client->searchDestination($data['q'], 10));
    }

    /**
     * Anak sebuah wilayah: ?parent=<id regions> → kota/kecamatan/kelurahan;
     * tanpa parent → daftar provinsi. Diambil dari API sekali per induk.
     */
    public function regions(Request $request, CourierRegions $regions, RajaOngkirClient $client): JsonResponse
    {
        $data = $request->validate(['parent' => ['nullable', 'integer', 'exists:regions,id']]);

        if (! $client->enabled()) {
            return response()->json([]);
        }

        $rows = empty($data['parent'])
            ? $regions->provinces()
            : $regions->children(Region::findOrFail((int) $data['parent']));

        return response()->json($rows->map(fn (Region $r) => [
            'id' => $r->id, 'code' => $r->code, 'name' => $r->name, 'postal_code' => $r->postal_code,
        ])->values());
    }
}
