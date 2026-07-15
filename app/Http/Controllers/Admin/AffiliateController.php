<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $affiliates = Affiliate::with('user')
            ->withCount('clicks')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliates.index', [
            'affiliates' => $affiliates,
            'status' => $status,
            'statuses' => AffiliateStatus::options(),
            'counts' => [
                'pending' => Affiliate::where('status', AffiliateStatus::Pending->value)->count(),
                'active' => Affiliate::where('status', AffiliateStatus::Active->value)->count(),
            ],
        ]);
    }

    public function show(Affiliate $affiliate): View
    {
        $affiliate->load('user', 'verifier');

        return view('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'commissions' => $affiliate->commissions()->with('order:id,order_number', 'product:id,name')->latest()->take(30)->get(),
            'payouts' => $affiliate->payouts()->latest()->get(),
            'stats' => [
                'pending' => $affiliate->pendingTotal(),
                'approved' => $affiliate->approvedTotal(),
                'paid' => $affiliate->paidTotal(),
                'available' => $affiliate->availableBalance(),
            ],
        ]);
    }

    /** Approve an application: verify data and activate the account. */
    public function verify(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update([
            'status' => AffiliateStatus::Active,
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        $this->notifications->toUser(
            $affiliate->user,
            'Akun afiliasi disetujui 🎉',
            "Selamat! Akun afiliasi Anda sudah aktif. Kode referral Anda: {$affiliate->code}. Mulai bagikan link Anda sekarang.",
            route('account.affiliate.dashboard'),
            'info',
            true,
            'Buka Dashboard',
        );

        return back()->with('success', "Afiliator {$affiliate->full_name} diverifikasi & diaktifkan.");
    }

    public function reject(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update([
            'status' => AffiliateStatus::Rejected,
            'note' => $request->input('note', $affiliate->note),
        ]);

        return back()->with('success', 'Pendaftaran afiliator ditolak.');
    }

    public function suspend(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update(['status' => AffiliateStatus::Suspended]);

        return back()->with('success', 'Afiliator ditangguhkan.');
    }

    public function reactivate(Affiliate $affiliate): RedirectResponse
    {
        $affiliate->update([
            'status' => AffiliateStatus::Active,
            'verified_at' => $affiliate->verified_at ?? now(),
            'verified_by' => $affiliate->verified_by ?? auth()->id(),
        ]);

        return back()->with('success', 'Afiliator diaktifkan kembali.');
    }
}
