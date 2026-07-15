<?php

namespace App\Http\Controllers;

use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Models\Affiliate;
use App\Services\AffiliateService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function __construct(
        private readonly AffiliateService $affiliates,
        private readonly NotificationService $notifications,
    ) {
    }

    /** Public program landing page. */
    public function landing(): View
    {
        $affiliate = auth()->user()?->affiliate;

        return view('affiliate.landing', [
            'affiliate' => $affiliate,
            'defaultRate' => $this->affiliates->defaultRate(),
            'minPayout' => $this->affiliates->minPayout(),
        ]);
    }

    /** Registration form (auth). */
    public function create(): View|RedirectResponse
    {
        if (auth()->user()->affiliate) {
            return redirect()->route('account.affiliate.dashboard');
        }

        return view('affiliate.register', ['user' => auth()->user()]);
    }

    /** Submit an affiliate application (goes to Pending for admin verification). */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->affiliate) {
            return redirect()->route('account.affiliate.dashboard');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
            'address' => ['required', 'string', 'max:1000'],
            'npwp' => ['required', 'string', 'max:40'],
            'ktp_photo' => ['required', 'image', 'max:4096'],
            'selfie_photo' => ['required', 'image', 'max:4096'],
            'channel' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:60'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
            'agree' => ['accepted'],
        ], [], [
            'npwp' => 'NPWP',
            'ktp_photo' => 'Foto KTP',
            'selfie_photo' => 'Foto Selfie',
        ]);

        unset($data['agree']);

        // KYC photos go on the PRIVATE disk (sensitive PII) — served only to admins.
        $data['ktp_photo_path'] = $request->file('ktp_photo')->store('affiliate-kyc', 'local');
        $data['selfie_photo_path'] = $request->file('selfie_photo')->store('affiliate-kyc', 'local');
        unset($data['ktp_photo'], $data['selfie_photo']);

        $data['user_id'] = $user->id;
        $data['status'] = AffiliateStatus::Pending;
        $data['code'] = $this->affiliates->generateCode($data['full_name']);

        Affiliate::create($data);

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

    /** Update payout (bank) details. */
    public function updateBank(Request $request): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate, 404);

        $affiliate->update($request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'bank_account_number' => ['required', 'string', 'max:60'],
            'bank_account_holder' => ['required', 'string', 'max:255'],
        ]));

        return back()->with('success', 'Data rekening diperbarui.');
    }

    /** Request a withdrawal. */
    public function requestPayout(Request $request): RedirectResponse
    {
        $affiliate = $request->user()->affiliate;
        abort_unless($affiliate && $affiliate->isActive(), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->affiliates->requestPayout($affiliate, (float) $validated['amount']);

        return back()->with('success', 'Permintaan penarikan dana terkirim. Menunggu diproses admin.');
    }
}
