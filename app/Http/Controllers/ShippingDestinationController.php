<?php

namespace App\Http\Controllers;

use App\Services\Shipping\RajaOngkirClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Autocomplete kelurahan/kecamatan tujuan (form alamat) dari RajaOngkir.
 * Hasil di-cache di klien API supaya kuota harian tidak habis oleh ketikan.
 */
class ShippingDestinationController extends Controller
{
    public function search(Request $request, RajaOngkirClient $client): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:100']]);

        if (! $client->enabled()) {
            return response()->json([]);
        }

        return response()->json($client->searchDestination($data['q'], 10));
    }
}
