<?php

namespace App\Http\Controllers;

use App\Services\AssistantAnalytics;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Storefront CS chat assistant endpoint. Grounds answers in the product
 * catalogue via AssistantService (Claude). Rate-limited in the route; each
 * exchange is logged (transcript + rollups) via AssistantAnalytics.
 */
class AssistantController extends Controller
{
    public function chat(Request $request, AssistantService $assistant, AssistantAnalytics $analytics): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'session_id' => ['sometimes', 'nullable', 'string', 'max:64'],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:4000'],
        ]);

        $result = $assistant->ask($data['message'], $data['history'] ?? []);

        $analytics->record($data['message'], $result, $data['session_id'] ?? null, $request->ip());

        return response()->json([
            'reply' => $result['reply'],
            'products' => $result['products'],
            'escalate' => $result['escalate'] ?? false,
            'whatsapp' => $result['whatsapp'] ?? null,
        ]);
    }
}
