<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Services\WaCampaign\WaContactService;
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

    /**
     * Daftar info promo dari footer. Dulu email newsletter (tidak pernah dikirimi apa pun);
     * sekarang nomor WhatsApp → langsung jadi kontak ber-izin di WA Campaign, dengan bukti
     * izin (waktu & IP). Email lama tetap diterima agar form versi cache tidak error.
     */
    public function subscribe(Request $request, WaContactService $contacts): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required_without:email', 'nullable', 'string', 'max:30'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:191'],
        ], ['phone.required_without' => 'Isi nomor WhatsApp Anda.']);

        if (! empty($data['phone'])) {
            $contact = $contacts->upsert($data['phone'], ['source' => 'footer']);
            if (! $contact) {
                return back()->withInput()->with('error', 'Nomor WhatsApp tidak valid. Contoh: 0812xxxxxxxx.');
            }
            $contacts->optIn($contact, 'footer', 'Daftar info promo via form website pada '.now()->format('d/m/Y H:i').' dari IP '.$request->ip());

            return back()->with('success', 'Terima kasih! Info promo akan dikirim ke WhatsApp Anda. Balas STOP kapan saja untuk berhenti.');
        }

        NewsletterSubscriber::updateOrCreate(['email' => $data['email']], ['is_active' => true, 'subscribed_at' => now()]);

        return back()->with('success', 'Terima kasih telah berlangganan info promo.');
    }
}
