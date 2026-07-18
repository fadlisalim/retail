<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AffiliateStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class AffiliateController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /** Stream a private KYC document (KTP / selfie). Admin-only via the route's permission gate. */
    public function document(Affiliate $affiliate, string $type): StreamedResponse
    {
        $path = match ($type) {
            'ktp' => $affiliate->ktp_photo_path,
            'selfie' => $affiliate->selfie_photo_path,
            default => null,
        };

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
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
        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ], [], ['note' => 'Alasan penolakan']);

        $affiliate->update([
            'status' => AffiliateStatus::Rejected,
            'note' => $data['note'],
        ]);

        // Notify the applicant (in-app + email) with the reason so they can fix
        // the issue and re-apply.
        $this->notifications->toUser(
            $affiliate->user,
            'Pendaftaran afiliasi belum disetujui',
            "Mohon maaf, pendaftaran afiliasi Anda belum dapat kami setujui.\n\nAlasan: {$data['note']}\n\nSilakan perbaiki data yang dimaksud, lalu daftar kembali melalui halaman afiliasi.",
            route('account.affiliate.dashboard'),
            'warning',
            true,
            'Lihat Detail',
        );

        return back()->with('success', 'Pendaftaran ditolak. Alasan dikirim ke email & notifikasi pendaftar.');
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

    /** Permanently delete an affiliate — only if it has no commission history (accounting integrity). */
    public function destroy(Affiliate $affiliate): RedirectResponse
    {
        if ($affiliate->commissions()->exists()) {
            return back()->with('error', 'Afiliator memiliki riwayat komisi dan tidak dapat dihapus. Gunakan "Tangguhkan" untuk menonaktifkan.');
        }

        // Remove private KYC files, then the record.
        foreach ([$affiliate->ktp_photo_path, $affiliate->selfie_photo_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
        $affiliate->delete();

        return redirect()->route('admin.affiliates.index')->with('success', 'Afiliator dihapus permanen.');
    }
}
