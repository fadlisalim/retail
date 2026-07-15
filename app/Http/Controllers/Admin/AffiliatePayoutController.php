<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliatePayout;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AffiliatePayoutController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $payouts = AffiliatePayout::with('affiliate.user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.affiliates.payouts', [
            'payouts' => $payouts,
            'status' => $status,
            'pendingCount' => AffiliatePayout::where('status', PayoutStatus::Requested->value)->count(),
        ]);
    }

    public function approve(AffiliatePayout $payout): RedirectResponse
    {
        abort_unless($payout->status === PayoutStatus::Requested, 422);

        $payout->update(['status' => PayoutStatus::Approved, 'processed_by' => auth()->id()]);

        return back()->with('success', 'Penarikan disetujui. Silakan transfer lalu tandai lunas.');
    }

    /** Mark as paid: settle the balance and consume approved commissions. */
    public function markPaid(Request $request, AffiliatePayout $payout): RedirectResponse
    {
        abort_if(in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Rejected], true), 422);

        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->affiliates->settlePayout($payout, auth()->id(), $data['reference'] ?? null);

        return back()->with('success', 'Penarikan ditandai lunas.');
    }

    public function reject(Request $request, AffiliatePayout $payout): RedirectResponse
    {
        abort_if(in_array($payout->status, [PayoutStatus::Paid, PayoutStatus::Rejected], true), 422);

        $payout->update([
            'status' => PayoutStatus::Rejected,
            'note' => $request->input('note', $payout->note),
            'processed_by' => auth()->id(),
            'processed_at' => now(),
        ]);

        return back()->with('success', 'Penarikan ditolak. Saldo dikembalikan.');
    }
}
