<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductImage;
use App\Models\ProductVideo;
use App\Services\VideoService;
use App\Services\WatermarkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Manages a product's media — gallery images, datasheet PDFs, and YouTube videos.
 * Kept separate from ProductController so each item can be added/removed without
 * re-submitting the whole product form. Files live on the public disk.
 */
class ProductMediaController extends Controller
{
    public function storeImage(Request $request, Product $produk, WatermarkService $watermark): RedirectResponse|JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('rekasurya.media.max_upload_kb', 15360)],
        ]);

        $next = (int) ($produk->images()->max('sort_order') ?? 0);
        foreach ($request->file('images') as $file) {
            $path = $file->store('products', 'public');

            // Optimise + watermark the freshly stored file. This may re-encode to
            // a smaller format (WebP) and return a new path — store that path.
            $optimised = $watermark->apply($path);

            $produk->images()->create([
                'path' => $optimised ?? $path,
                'alt' => $produk->name,
                'sort_order' => ++$next,
                'watermarked_at' => $optimised ? now() : null,
            ]);
        }

        // First (non-video) image uploaded becomes the main image if none is set.
        if (! $produk->main_image_path) {
            $produk->update(['main_image_path' => $produk->images()->whereNull('video_path')->orderBy('sort_order')->value('path')]);
        }

        // Upload cepat dari list produk (fetch) — balas JSON supaya thumbnail
        // di baris bisa diperbarui tanpa reload halaman.
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'url' => $produk->fresh()->primaryImageUrl(),
                'images_count' => $produk->images()->whereNull('video_path')->count(),
            ]);
        }

        return back()->with('success', 'Gambar berhasil diunggah.');
    }

    /**
     * Upload a short product video into the gallery. Compressed with ffmpeg when
     * available (else stored as-is, ≤20 MB). The browser-captured first frame is
     * used as the poster; the video takes its place in the sortable gallery order.
     */
    public function storeVideoFile(Request $request, Product $produk, VideoService $video, WatermarkService $watermark): RedirectResponse
    {
        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.(int) config('rekasurya.media.max_video_kb', 20480)],
            'poster' => ['nullable', 'image', 'max:5120'],
        ]);

        $disk = Storage::disk('public');
        $stored = $request->file('video')->store('products/videos', 'public');

        // Compress with ffmpeg when available.
        if ($video->isFfmpegAvailable()) {
            $outRel = 'products/videos/'.pathinfo($stored, PATHINFO_FILENAME).'-opt.mp4';
            if ($video->compress($disk->path($stored), $disk->path($outRel))) {
                $disk->delete($stored);
                $stored = $outRel;
            }
        }

        // Poster: browser-captured frame first, else an ffmpeg frame, else main image.
        $posterPath = null;
        if ($request->hasFile('poster')) {
            $p = $request->file('poster')->store('products', 'public');
            $posterPath = $watermark->apply($p) ?? $p;
        } elseif ($video->isFfmpegAvailable()) {
            $posterRel = 'products/'.pathinfo($stored, PATHINFO_FILENAME).'-poster.jpg';
            if ($video->extractPoster($disk->path($stored), $disk->path($posterRel))) {
                $posterPath = $watermark->apply($posterRel) ?? $posterRel;
            }
        }
        $posterPath = $posterPath ?: $produk->main_image_path;

        $next = (int) ($produk->images()->max('sort_order') ?? 0);
        $produk->images()->create([
            'path' => $posterPath ?: '',
            'video_path' => $stored,
            'alt' => $produk->name,
            'sort_order' => ++$next,
        ]);

        return back()->with('success', 'Video pendek berhasil diunggah.');
    }

    public function setPrimaryImage(Product $produk, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $produk->id, 404);
        // The catalogue thumbnail must be a photo, never a video.
        if ($image->isVideo()) {
            return back()->with('error', 'Video tidak bisa dijadikan gambar utama.');
        }
        $produk->update(['main_image_path' => $image->path]);

        return back()->with('success', 'Gambar utama diperbarui.');
    }

    /** Persist a new gallery order (array of image ids in the desired sequence). */
    public function reorderImages(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        // Only touch images that belong to this product.
        $valid = $produk->images()->pluck('id')->all();
        $position = 0;
        foreach ($data['order'] as $id) {
            if (in_array((int) $id, $valid, true)) {
                ProductImage::where('id', $id)->update(['sort_order' => ++$position]);
            }
        }

        return back()->with('success', 'Urutan gambar disimpan.');
    }

    public function destroyImage(ProductImage $image): RedirectResponse
    {
        $product = $image->product;
        Storage::disk('public')->delete($image->path);
        if ($image->video_path) {
            Storage::disk('public')->delete($image->video_path);
        }
        $wasPrimary = ! $image->isVideo() && $product && $product->main_image_path === $image->path;
        $image->delete();

        if ($wasPrimary) {
            $product->update(['main_image_path' => $product->images()->whereNull('video_path')->orderBy('sort_order')->value('path')]);
        }

        return back()->with('success', ($image->isVideo() ? 'Video' : 'Gambar').' dihapus.');
    }

    public function storeDocument(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', 'string', 'max:40'],
            'document' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $path = $request->file('document')->store('products/docs', 'public');
        $produk->documents()->create(['type' => $data['type'], 'title' => $data['title'], 'path' => $path]);

        return back()->with('success', 'Dokumen berhasil diunggah.');
    }

    public function destroyDocument(ProductDocument $dokumen): RedirectResponse
    {
        Storage::disk('public')->delete($dokumen->path);
        $dokumen->delete();

        return back()->with('success', 'Dokumen dihapus.');
    }

    public function storeVideo(Request $request, Product $produk): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'url' => ['required', 'url', 'max:255'],
        ]);

        $produk->videos()->create(['title' => $data['title'] ?: 'Video Produk', 'url' => $data['url']]);

        return back()->with('success', 'Video ditambahkan.');
    }

    public function destroyVideo(ProductVideo $video): RedirectResponse
    {
        $video->delete();

        return back()->with('success', 'Video dihapus.');
    }
}
