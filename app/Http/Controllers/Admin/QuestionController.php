<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductAnswer;
use App\Models\ProductQuestion;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Moderasi Tanya Jawab produk. Jawaban admin tampil di halaman produk dan
 * dikirim ke WhatsApp penanya (sekali per jawaban, ditandai wa_notified_at).
 */
class QuestionController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->input('filter', 'unanswered');

        $questions = ProductQuestion::query()
            ->with(['product:id,name,slug', 'answers'])
            ->when($filter === 'unanswered', fn ($q) => $q->whereDoesntHave('answers'))
            ->when($filter === 'answered', fn ($q) => $q->whereHas('answers'))
            ->when($filter === 'hidden', fn ($q) => $q->where('is_visible', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.questions.index', [
            'questions' => $questions,
            'filter' => $filter,
            'unansweredCount' => ProductQuestion::whereDoesntHave('answers')->count(),
        ]);
    }

    public function answer(Request $request, ProductQuestion $question, WhatsAppService $wa): RedirectResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $answer = $question->answers()->create([
            'user_id' => $request->user()->id,
            'is_staff' => true,
            'answer' => $data['answer'],
        ]);

        $notified = $this->notifyAsker($question, $answer, $wa);

        return back()->with('success', 'Jawaban tersimpan.'.match ($notified) {
            true => ' Notifikasi WhatsApp terkirim ke penanya.',
            false => ' (Notifikasi WA gagal terkirim — cek koneksi Wablas.)',
            null => '',
        });
    }

    public function toggleVisibility(ProductQuestion $question): RedirectResponse
    {
        $question->update(['is_visible' => ! $question->is_visible]);

        return back()->with('success', $question->is_visible ? 'Pertanyaan ditampilkan.' : 'Pertanyaan disembunyikan.');
    }

    /** true = terkirim, false = gagal, null = tidak ada nomor / sudah pernah. */
    private function notifyAsker(ProductQuestion $question, ProductAnswer $answer, WhatsAppService $wa): ?bool
    {
        $phone = $wa->normalize($question->phone);
        if (! $phone || $answer->wa_notified_at) {
            return null;
        }

        $product = $question->product;
        $message = "Halo Kak {$question->name} 👋\n\n"
            ."Pertanyaan Kakak tentang *{$product->name}* sudah kami jawab:\n\n"
            .'T: '.Str::limit($question->question, 300)."\n"
            .'J: '.$answer->answer."\n\n"
            .'Selengkapnya: '.route('products.show', $product->slug)."\n\n"
            .'— '.brand();

        $ok = $wa->send($phone, $message);
        if ($ok) {
            $answer->forceFill(['wa_notified_at' => now()])->save();
        }

        return $ok;
    }
}
