<?php

namespace App\Http\Controllers;

use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront CS chat assistant endpoint. Grounds answers in the product
 * catalogue via AssistantService (Claude). Rate-limited in the route.
 */
class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $result = $assistant->ask($data['message'], $data['history'] ?? []);

        return response()->json([
            'reply' => $result['reply'],
            'products' => $result['products'],
        ]);
    }
}
