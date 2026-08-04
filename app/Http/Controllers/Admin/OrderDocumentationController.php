<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDocumentation;
use App\Services\WatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin-side upload of per-order documentation photos (prepared, tested,
 * packed, shipped, installed). Photos are watermarked/optimised like product
 * images; the ones flagged public also feed the storefront gallery.
 */
class OrderDocumentationController extends Controller
{
    public function store(Request $request, Order $order, WatermarkService $watermark): RedirectResponse
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(array_keys(OrderDocumentation::STAGES))],
            'caption' => ['nullable', 'string', 'max:191'],
            'is_public' => ['nullable', 'boolean'],
            'photos' => ['required', 'array', 'max:12'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('rekasurya.media.max_upload_kb', 15360)],
        ]);

        $next = (int) ($order->documentations()->max('sort_order') ?? 0);

        foreach ($request->file('photos') as $file) {
            $path = $file->store('dokumentasi', 'public');
            // Same treatment as product photos: optimise + watermark. Returns a
            // new path when re-encoded (e.g. to WebP).
            $optimised = $watermark->apply($path);

            $order->documentations()->create([
                'stage' => $data['stage'],
                'path' => $optimised ?? $path,
                'caption' => $data['caption'] ?? null,
                'is_public' => $request->boolean('is_public'),
                'sort_order' => ++$next,
                'created_by' => $request->user()?->id,
            ]);
        }

        return back()->with('success', 'Foto dokumentasi berhasil diunggah.');
    }

    /** Flip whether a photo may appear in the public gallery. */
    public function togglePublic(Order $order, OrderDocumentation $documentation): RedirectResponse
    {
        abort_unless($documentation->order_id === $order->id, 404);

        $documentation->update(['is_public' => ! $documentation->is_public]);

        return back()->with('success', $documentation->is_public
            ? 'Foto ditampilkan di galeri publik.'
            : 'Foto disembunyikan dari galeri publik.');
    }

    public function destroy(Order $order, OrderDocumentation $documentation): RedirectResponse
    {
        abort_unless($documentation->order_id === $order->id, 404);

        Storage::disk('public')->delete($documentation->path);
        $documentation->delete();

        return back()->with('success', 'Foto dokumentasi dihapus.');
    }
}
