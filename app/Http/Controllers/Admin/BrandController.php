<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BrandController extends Controller
{
    public function index(): View
    {
        $brands = Brand::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.brands.index', compact('brands'));
    }

    /**
     * Unduh semua foto (galeri, foto utama, foto varian) & dokumen produk satu brand
     * dalam satu ZIP, dikelompokkan per produk — untuk materi marketing/reseller.
     */
    public function media(Brand $brand): BinaryFileResponse|RedirectResponse
    {
        if (! class_exists(\ZipArchive::class)) {
            return back()->with('error', 'Ekstensi PHP zip belum aktif di server — ZIP tidak bisa dibuat.');
        }

        $disk = Storage::disk('public');
        $tmp = (string) tempnam(sys_get_temp_dir(), 'media');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $added = 0;
        $add = function (?string $path, string $name) use ($disk, $zip, &$added): void {
            if ($path && $disk->exists($path) && $zip->locateName($name) === false) {
                $zip->addFile($disk->path($path), $name);
                $added++;
            }
        };

        $products = $brand->products()->with(['images', 'documents', 'variants'])->orderBy('name')->get();
        foreach ($products as $product) {
            $dir = Str::limit(Str::slug($product->name), 80, '');
            $add($product->main_image_path, "{$dir}/foto/".basename((string) $product->main_image_path));
            foreach ($product->images->whereNull('video_path') as $image) {
                $add($image->path, "{$dir}/foto/".basename($image->path));
            }
            foreach ($product->variants as $variant) {
                $add($variant->image_path, "{$dir}/foto-varian/".Str::slug($variant->name).'.'.pathinfo((string) $variant->image_path, PATHINFO_EXTENSION));
            }
            foreach ($product->documents as $doc) {
                $add($doc->path, "{$dir}/dokumen/".Str::limit(Str::slug($doc->title), 80, '').'.'.pathinfo($doc->path, PATHINFO_EXTENSION));
            }
        }
        $zip->close();

        if (! $added) {
            @unlink($tmp);

            return back()->with('warning', "Brand {$brand->name} belum punya foto/dokumen produk.");
        }

        return response()->download($tmp, 'media-'.$brand->slug.'-'.now()->format('Ymd').'.zip', ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    public function create(): View
    {
        $brand = new Brand(['is_active' => true, 'sort_order' => 0]);

        return view('admin.brands.create', compact('brand'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $logo = $this->handleLogo($request, null);
        if ($logo !== false) {
            $data['logo_path'] = $logo;
        }

        Brand::create($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand berhasil ditambahkan.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request, $brand);

        $logo = $this->handleLogo($request, $brand);
        if ($logo !== false) {
            $data['logo_path'] = $logo;
        }

        $brand->update($data);

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand berhasil diperbarui.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->logo_path) {
            Storage::disk('public')->delete($brand->logo_path);
        }
        $brand->delete();

        return redirect()->route('admin.brands.index')
            ->with('success', 'Brand berhasil dihapus.');
    }

    /**
     * Resolve the logo change: new upload → stored path (old removed); "remove"
     * checked → null (old removed); otherwise false (leave logo_path untouched).
     */
    private function handleLogo(Request $request, ?Brand $brand): string|null|false
    {
        $disk = Storage::disk('public');

        if ($request->hasFile('logo')) {
            if ($brand?->logo_path) {
                $disk->delete($brand->logo_path);
            }

            return $request->file('logo')->store('brands', 'public');
        }

        if ($request->boolean('remove_logo')) {
            if ($brand?->logo_path) {
                $disk->delete($brand->logo_path);
            }

            return null;
        }

        return false;
    }

    private function validated(Request $request, ?Brand $brand): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('name')),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('brands', 'slug')->ignore($brand?->id)],
            'description' => ['nullable', 'string'],
            'website' => ['nullable', 'string', 'max:255'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        // Logo file is handled separately (see handleLogo); not a mass-assignable column.
        unset($data['logo'], $data['remove_logo']);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
