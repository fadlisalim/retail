<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $category = new Category(['is_active' => true, 'sort_order' => 0]);

        return view('admin.categories.create', [
            'category' => $category,
            'parents' => $this->parentOptions(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        $category = Category::create($data);
        $this->syncTree($category);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Category $kategori): View
    {
        return view('admin.categories.edit', [
            'category' => $kategori,
            'parents' => $this->parentOptions($kategori),
        ]);
    }

    public function update(Request $request, Category $kategori): RedirectResponse
    {
        $data = $this->validated($request, $kategori);

        $kategori->update($data);
        $this->syncTree($kategori);

        return redirect()->route('admin.categories.index')
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $kategori): RedirectResponse
    {
        $kategori->delete();

        return redirect()->route('admin.categories.index')
            ->with('success', 'Kategori berhasil dihapus.');
    }

    private function validated(Request $request, ?Category $category): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('name')),
        ]);

        $parentRules = ['nullable', 'integer', 'exists:categories,id'];
        if ($category) {
            $parentRules[] = Rule::notIn([$category->id]);
        }

        $data = $request->validate([
            'parent_id' => $parentRules,
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['parent_id'] = $data['parent_id'] ?? null;

        return $data;
    }

    /** Recompute materialised depth/path from the parent after the row has an id. */
    private function syncTree(Category $category): void
    {
        if ($category->parent_id && ($parent = Category::find($category->parent_id))) {
            $depth = (int) $parent->depth + 1;
            $path = ($parent->path ? $parent->path.'/' : '').$category->id;
        } else {
            $depth = 0;
            $path = (string) $category->id;
        }

        $category->forceFill(['depth' => $depth, 'path' => $path])->save();
    }

    /** Options for the parent select, excluding the category itself. */
    private function parentOptions(?Category $category): array
    {
        return Category::query()
            ->when($category, fn ($q) => $q->whereKeyNot($category->id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
