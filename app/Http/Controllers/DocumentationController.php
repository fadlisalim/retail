<?php

namespace App\Http\Controllers;

use App\Models\OrderDocumentation;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public "Dokumentasi Pengerjaan" gallery: real photos of how orders are
 * prepared, tested, packed and shipped. Anonymous by design — only the stage,
 * caption and month are shown, never the customer or order number.
 */
class DocumentationController extends Controller
{
    public function index(Request $request): View
    {
        $stage = $request->query('tahap');
        $stage = array_key_exists((string) $stage, OrderDocumentation::STAGES) ? $stage : null;

        $photos = OrderDocumentation::query()
            ->where('is_public', true)
            ->when($stage, fn ($q) => $q->where('stage', $stage))
            ->latest('created_at')
            ->paginate(24)
            ->withQueryString();

        $counts = OrderDocumentation::where('is_public', true)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        return view('storefront.documentation', [
            'photos' => $photos,
            'stage' => $stage,
            'counts' => $counts,
            'total' => (int) $counts->sum(),
        ]);
    }
}
