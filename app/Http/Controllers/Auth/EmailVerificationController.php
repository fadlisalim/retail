<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    /** Prompt shown to any logged-in but unverified user (edge case). */
    public function notice(Request $request): View|RedirectResponse
    {
        return $request->user()->hasVerifiedEmail()
            ? redirect()->route('account.dashboard')
            : view('auth.verify-email');
    }

    /**
     * Handle the signed verification link. Works WITHOUT the user being logged in
     * (they verify before their first login), so the user is resolved from the URL.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if (! $user || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Tautan verifikasi tidak valid atau sudah kedaluwarsa.']);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('login')->with('success', 'Email sudah terverifikasi. Silakan masuk.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect()->route('login')->with('success', 'Email berhasil diverifikasi. Silakan masuk.');
    }

    /**
     * Resend the verification link. Works for a logged-in unverified user, or for a
     * guest who supplies matching email + password (so only the owner can trigger it).
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            $data = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);
            $candidate = User::where('email', $data['email'])->first();
            if ($candidate && Hash::check($data['password'], $candidate->password)) {
                $user = $candidate;
            }
        }

        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        // Uniform response — don't reveal whether an email/password matched.
        return back()->with('status', 'Jika data cocok dan email belum terverifikasi, tautan verifikasi baru telah dikirim.');
    }
}
