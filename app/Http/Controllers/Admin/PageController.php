<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::orderBy('title')->paginate(20);

        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        $page = new Page(['is_published' => true]);

        return view('admin.pages.create', compact('page'));
    }

    public function store(Request $request): RedirectResponse
    {
        Page::create($this->validated($request, null));

        return redirect()->route('admin.pages.index')
            ->with('success', 'Halaman berhasil ditambahkan.');
    }

    public function edit(Page $halaman): View
    {
        return view('admin.pages.edit', ['page' => $halaman]);
    }

    public function update(Request $request, Page $halaman): RedirectResponse
    {
        $halaman->update($this->validated($request, $halaman));

        return redirect()->route('admin.pages.index')
            ->with('success', 'Halaman berhasil diperbarui.');
    }

    public function destroy(Page $halaman): RedirectResponse
    {
        $halaman->delete();

        return redirect()->route('admin.pages.index')
            ->with('success', 'Halaman berhasil dihapus.');
    }

    private function validated(Request $request, ?Page $page): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('title')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('pages', 'slug')->ignore($page?->id)],
            'content' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
