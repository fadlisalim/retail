<?php

namespace App\Http\Controllers;

use App\Models\DeployProposal;
use App\Services\DeployProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Halaman laporan usulan agent untuk pemilik (link ber-kunci dari WA). Keputusan
 * (Eksekusi / Revisi-Tolak) wajib OTP yang dikirim ke WA pemilik.
 */
class DeployProposalController extends Controller
{
    public function __construct(private readonly DeployProposalService $proposals) {}

    public function show(string $token): View
    {
        $proposal = $this->find($token);

        return view('deploy-proposal', [
            'proposal' => $proposal,
            'html' => Str::markdown(preg_replace('/^#\s+.+$/m', '', $proposal->report, 1), // judul sudah tampil di header
                ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'phone' => Str::mask($this->proposals->ownerPhone(), '•', 4, -3),
            'ready' => (bool) $this->proposals->githubToken(),
        ]);
    }

    public function otp(string $token): JsonResponse
    {
        try {
            $this->proposals->sendOtp($this->find($token));
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        return response()->json(['message' => 'Kode OTP dikirim ke WhatsApp bro (berlaku '.DeployProposalService::OTP_TTL_MINUTES.' menit).']);
    }

    public function decide(Request $request, string $token): RedirectResponse
    {
        $proposal = $this->find($token);
        $data = $request->validate([
            'action' => ['required', Rule::in(['execute', 'reject'])],
            'note' => [Rule::requiredIf($request->input('action') === 'reject'), 'nullable', 'string', 'max:3000'],
            'otp' => ['required', 'digits:6'],
        ], ['note.required' => 'Tulis saran/alasan revisi supaya agent bisa memperbaikinya.']);

        $this->proposals->decide($proposal, $data['action'], $data['note'] ?? null, $data['otp']);

        return back()->with('success', $data['action'] === 'execute'
            ? '✅ Dieksekusi — perubahan tayang di energi.click ±2 menit lagi.'
            : '✏️ Dicatat — usulan ditutup, saran bro dipakai agent di putaran berikutnya.');
    }

    private function find(string $token): DeployProposal
    {
        abort_unless(strlen($token) === 48, 404);

        return DeployProposal::where('token', $token)->firstOrFail();
    }
}
