<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionStatus;
use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Services\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Review komisi dari atribusi afiliator MANUAL (admin memilih afiliator di
 * input pesanan, atau command affiliate:attribute). Hanya super admin yang
 * boleh meloloskan — mencegah admin biasa mengaitkan pesanan ke afiliator
 * sembarangan.
 */
class AffiliateCommissionReviewController extends Controller
{
    public function __construct(private readonly AffiliateService $affiliates) {}

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        // public_token ikut dipilih: route admin.orders.show mengikat pesanan lewat token itu.
        $with = ['affiliate.user:id,name', 'order:id,order_number,public_token,status,payment_status,customer_name,grand_total', 'product:id,name', 'attributedBy:id,name', 'reviewedBy:id,name'];

        return view('admin.affiliates.review', [
            'commissions' => AffiliateCommission::with($with)
                ->where('status', CommissionStatus::AwaitingReview->value)
                ->oldest()->paginate(30),
            'recent' => AffiliateCommission::with($with)
                ->whereNotNull('reviewed_at')->latest('reviewed_at')->take(20)->get(),
        ]);
    }

    public function approve(Request $request, AffiliateCommission $commission): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        if ($commission->status !== CommissionStatus::AwaitingReview) {
            return back()->with('error', 'Komisi ini sudah direview.');
        }

        $this->affiliates->reviewApprove($commission, $request->user());

        return back()->with('success', 'Komisi '.rupiah($commission->amount).' disetujui — '.$commission->fresh()->status->label().'.');
    }

    public function reject(Request $request, AffiliateCommission $commission): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        $data = $request->validate(['note' => ['required', 'string', 'max:255']], ['note.required' => 'Tulis alasan penolakan.']);

        if ($commission->status !== CommissionStatus::AwaitingReview) {
            return back()->with('error', 'Komisi ini sudah direview.');
        }

        $this->affiliates->reviewReject($commission, $request->user(), $data['note']);

        return back()->with('success', 'Komisi ditolak.');
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Hanya Super Admin yang boleh mereview komisi afiliator.');
    }
}
