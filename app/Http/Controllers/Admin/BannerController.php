<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BannerController extends Controller
{
    public function index(): View
    {
        $banners = Banner::orderBy('position')
            ->orderBy('sort_order')
            ->paginate(20);

        return view('admin.banners.index', compact('banners'));
    }

    public function create(): View
    {
        $banner = new Banner(['position' => 'hero', 'span' => 'third', 'is_active' => true, 'sort_order' => 0]);

        return view('admin.banners.create', compact('banner'));
    }

    public function store(Request $request): RedirectResponse
    {
        Banner::create($this->validated($request, null));

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner berhasil ditambahkan.');
    }

    public function edit(Banner $banner): View
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $banner->update($this->validated($request, $banner));

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner berhasil diperbarui.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $banner->delete();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner berhasil dihapus.');
    }

    private function validated(Request $request, ?Banner $banner): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'button_text' => ['nullable', 'string', 'max:255'],
            'button_url' => ['nullable', 'string', 'max:255'],
            'position' => ['required', Rule::in(['hero', 'grid', 'video', 'promo', 'quotation'])],
            'span' => ['required', Rule::in(['full', 'half', 'third'])],
            'is_portrait' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image_desktop' => ['nullable', 'image', 'max:2048'],
            'image_mobile' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_portrait'] = $request->boolean('is_portrait');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        // Banners run by whole days: a start date is active from 00:00, an end date
        // through 23:59 — so a banner set to start "today" shows immediately.
        if (! empty($data['starts_at'])) {
            $data['starts_at'] = \Illuminate\Support\Carbon::parse($data['starts_at'])->startOfDay();
        }
        if (! empty($data['ends_at'])) {
            $data['ends_at'] = \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay();
        }

        // Store uploaded images; keep existing paths when no new file is provided.
        if ($request->hasFile('image_desktop')) {
            $data['image_desktop_path'] = $request->file('image_desktop')->store('banners', 'public');
        }
        if ($request->hasFile('image_mobile')) {
            $data['image_mobile_path'] = $request->file('image_mobile')->store('banners', 'public');
        }

        unset($data['image_desktop'], $data['image_mobile']);

        return $data;
    }
}
