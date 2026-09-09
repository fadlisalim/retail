<?php

namespace App\Http\Controllers;

use App\Enums\AffiliateStatus;
use App\Enums\PayoutStatus;
use App\Mail\AffiliateBankChangedMail;
use App\Mail\AffiliatePayoutConfirmMail;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Models\Product;
use App\Services\AffiliateService;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function __construct(
        private readonly AffiliateService $affiliates,
        private readonly NotificationService $notifications,
    ) {}

    /** Public program landing page — termasuk tabel fee komisi per produk. */
    public function landing(Request $request): View
    {
        $affiliate = auth()->user()?->affiliate;
        $defaultRate = $this->affiliates->defaultRate();

        // Tabel komisi publik: urut fee tertinggi, jadi materi rekrutmen
        // sekaligus katalog kerja untuk afiliator yang sudah aktif.
        $products = Product::query()
            ->where('status', 'published')
            ->where('is_purchasable', true)
            ->where('requires_quotation', false)
            ->with(['brand:id,name', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->orderByRaw('COALESCE(affiliate_rate, ?) DESC', [$defaultRate])
            ->orderByDesc('price')
            // 6 kartu per halaman: fee terbesar langsung terlihat, sisanya
            // lewat tombol halaman berikutnya.
            ->paginate(6, ['*'], 'hal')
            ->withQueryString();

        return view('affiliate.landing', [
            'affiliate' => $affiliate,
            'defaultRate' => $defaultRate,
            'minPayout' => $this->affiliates->minPayout(),
            'products' => $products,
        ]);
    }

    /** Registration form (auth). */
    public function create(): View|RedirectResponse
    {
        $affiliate = auth()->user()->affiliate;

        // A rejected applicant may re-apply; any other existing record goes to the dashboard.
        if ($affiliate && $affiliate->status !== AffiliateStatus::Rejected) {
            return redirect()->route('account.affiliate.dashboard');
        }

        return view('affiliate.register', ['user' => auth()->user()]);
    }

    /** Submit an affiliate application (goes to Pending for admin verification). */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $existing = $user->affiliate;

        // Only a rejected application may be resubmitted; anything else is already handled.
        if ($existing && $existing->status !== AffiliateStatus::Rejected) {
            return redirect()->route('account.affiliate.dashboard');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:1000'],
            'npwp' => ['nullable', 'string', 'max:40'],
            'ktp_photo' => ['required', 'image', 'max:4096'],
            'selfie_photo' => ['required', 'image', 'max:4096'],
            'channel' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:60'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
        ], [], [
            'ktp_photo' => 'Foto KTP',
            'selfie_photo' => 'Foto Selfie',
        ]);

        unset($data['agree']);

        // Store contact number in international format (08… → 62…).
        $data['phone'] = app(WhatsAppService::class)->normalize($data['phone']) ?? $data['phone'];

        // KYC photos go on the PRIVATE disk (sensitive PII) — served only to admins.
        $data['ktp_photo_path'] = $request->file('ktp_photo')->store('affiliate-kyc', 'local');
        $data['selfie_photo_path'] = $request->file('selfie_photo')->store('affiliate-kyc', 'local');
        unset($data['ktp_photo'], $data['selfie_photo']);

        $data['status'] = AffiliateStatus::Pending;

        if ($existing) {
            // Re-application after rejection: swap in the new KYC photos, reset to
            // pending, and clear the previous rejection note.
            foreach ([$existing->ktp_photo_path, $existing->selfie_photo_path] as $old) {
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            }
            $data['note'] = null;
            $existing->update($data);
        } else {
            $data['user_id'] = $user->id;
            $data['code'] = $this->affiliates->generateCode();
            Affiliate::create($data);
        }

        $this->notifications->toUser(
            $user,
            'Pendaftaran afiliasi diterima',
            'Terima kasih, pendaftaran afiliasi Anda sedang kami verifikasi. Kami akan memberi tahu setelah disetujui.',
            route('account.affiliate.dashboard'),
            'info',
            true,
            'Lihat Status',
        );

        return redirect()->route('account.affiliate.dashboard')
            ->with('success', 'Pendaftaran afiliasi terkirim. Data Anda sedang diverifikasi admin.');
    }

    /** Affiliate dashboard (auth). */
    public function dashboard(): View|RedirectResponse
    {
        $affiliate = auth()->user()->affiliate;

        if (! $affiliate) {
            return redirect()->route('account.affiliate.register');
        }

        $commissions = $affiliate->commissions()
            ->with('order:id,order_number', 'product:id,name')
            ->latest()->paginate(15);

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'commissions' => $commissions,
            'payouts' => $affiliate->payouts()->latest()->take(10)->get(),
            'stats' => [
                'clicks' => $affiliate->clicks()->count(),
                'orders' => $affiliate->commissions()->distinct('order_id')->count('order_id'),
                'pending' => $affiliate->pendingTotal(),
                'approved' => $affiliate->approvedTotal(),
                'paid' => $affiliate->paidTotal(),
                'available' => $affiliate->availableBalance(),
            ],
            'minPayout' => $this->affiliates->minPayout(),
        ]);
    }

    /**
     * Katalog komisi untuk afiliator: produk diurutkan dari fee tertinggi,
     * lengkap dengan link referral per produk yang tinggal disalin.
     */
    public function products(Request $request): View|RedirectResponse
    {
        $affiliate = auth()->user()->affiliate;

        if (! $affiliate || ! $affiliate->isActive()) {
            return redirect()->route('account.affiliate.dashboard');
        }

        $q = trim((string) $request->query('q', ''));
        $defaultRate = $this->affiliates->defaultRate();

        $products = Product::query()
            ->where('status', 'published')
            ->where('is_purchasable', true)
            ->where('requires_quotation', false)
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->with(['brand:id,name', 'variants' => fn ($v) => $v->where('is_active', true)])
            // Fee efektif = rate produk, atau rate default toko bila kosong.
            ->orderByRaw('COALESCE(affiliate_rate, ?) DESC', [$defaultRate])
            ->orderByDesc('price')
            ->paginate(24)
            ->withQueryString();

        return view('affiliate.products', [
            'affiliate' => $affiliate,
            'products' => $products,
            'defaultRate' => $defaultRate,
            'q' => $q,
        ]);
    }

    /** Update payout (bank) details — pemiliknya diberi tahu lewat email. */
    public function updateBank(Request $request): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $affiliate->update($request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:60'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
        ]));

        // Sinyal keamanan: bila yang mengubah bukan pemilik akun, ia langsung
        // tahu dari email ini. Gagal kirim tidak membatalkan perubahan.
        try {
            Mail::to($request->user()->email)
                ->send(new AffiliateBankChangedMail($affiliate->fresh()));
        } catch (\Throwable $e) {
            Log::warning('Email notifikasi rekening afiliasi gagal: '.$e->getMessage());
        }

        return back()->with('success', 'Data rekening diperbarui. Pemberitahuan dikirim ke email Anda.');
    }

    /** Request a withdrawal — diproses setelah dikonfirmasi lewat email. */
    public function requestPayout(Request $request): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate && $affiliate->isActive(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $payout = $this->affiliates->requestPayout($affiliate, (float) $validated['amount']);

        $confirmUrl = URL::temporarySignedRoute(
            'account.affiliate.payout.confirm', now()->addHours(24), ['payout' => $payout->id],
        );

        try {
            Mail::to($request->user()->email)
                ->send(new AffiliatePayoutConfirmMail($payout, $confirmUrl));
        } catch (\Throwable $e) {
            // Tanpa email, penarikan tidak akan pernah bisa dikonfirmasi —
            // batalkan supaya saldo tidak terkunci.
            $payout->delete();
            Log::warning('Email konfirmasi penarikan gagal: '.$e->getMessage());

            return back()->withErrors(['amount' => 'Email konfirmasi gagal terkirim. Coba lagi sebentar, atau hubungi CS.']);
        }

        return back()->with('success', 'Cek email Anda ya — klik link konfirmasi (berlaku 24 jam) supaya penarikan diproses tim keuangan.');
    }

    /**
     * Konfirmasi penarikan dari link email (signed URL, 24 jam). Sengaja tidak
     * mewajibkan login: link hanya ada di inbox pemilik akun, dan tanda tangan
     * URL tidak bisa dipalsukan.
     */
    public function confirmPayout(AffiliatePayout $payout): RedirectResponse
    {
        if ($payout->status !== PayoutStatus::AwaitingConfirmation) {
            return redirect()->route('account.affiliate.dashboard')
                ->with('success', 'Penarikan ini sudah dikonfirmasi sebelumnya.');
        }

        $payout->update([
            'status' => PayoutStatus::Requested,
            'confirmed_at' => now(),
        ]);

        return redirect()->route('account.affiliate.dashboard')
            ->with('success', 'Penarikan dikonfirmasi! Tim keuangan akan segera memproses transfer Anda.');
    }
}
