<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function page(Page $page): View
    {
        abort_unless($page->is_published, 404);

        return view('storefront.page', ['page' => $page]);
    }

    public function articles(): View
    {
        return view('storefront.articles', [
            'articles' => Article::published()->latest('published_at')->paginate(9),
        ]);
    }

    public function article(Article $article): View
    {
        abort_unless($article->is_published, 404);
        $article->increment('view_count');

        return view('storefront.article', [
            'article' => $article,
            'related' => Article::published()->where('id', '!=', $article->id)->latest('published_at')->take(3)->get(),
        ]);
    }

    public function faq(): View
    {
        return view('storefront.faq', [
            'faqs' => Faq::where('is_active', true)->orderBy('category')->orderBy('sort_order')->get()->groupBy('category'),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:191']]);

        NewsletterSubscriber::updateOrCreate(
            ['email' => $data['email']],
            ['is_active' => true, 'subscribed_at' => now()],
        );

        return back()->with('success', 'Terima kasih telah berlangganan newsletter Rekasurya.');
    }
}
