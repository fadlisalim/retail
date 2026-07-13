<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = Article::with('author')->latest('id')->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function create(): View
    {
        $article = new Article(['is_published' => false]);

        return view('admin.articles.create', compact('article'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $data['author_id'] = auth()->id();

        Article::create($data);

        return redirect()->route('admin.articles.index')
            ->with('success', 'Artikel berhasil ditambahkan.');
    }

    public function edit(Article $artikel): View
    {
        return view('admin.articles.edit', ['article' => $artikel]);
    }

    public function update(Request $request, Article $artikel): RedirectResponse
    {
        $artikel->update($this->validated($request, $artikel));

        return redirect()->route('admin.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(Article $artikel): RedirectResponse
    {
        $artikel->delete();

        return redirect()->route('admin.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    private function validated(Request $request, ?Article $article): array
    {
        $request->merge([
            'slug' => $request->filled('slug')
                ? Str::slug($request->input('slug'))
                : Str::slug((string) $request->input('title')),
        ]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['nullable', 'string'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'cover' => ['nullable', 'image', 'max:2048'],
        ]);

        $data['is_published'] = $request->boolean('is_published');

        if ($data['is_published'] && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        if ($request->hasFile('cover')) {
            $data['cover_path'] = $request->file('cover')->store('articles', 'public');
        }

        unset($data['cover']);

        return $data;
    }
}
