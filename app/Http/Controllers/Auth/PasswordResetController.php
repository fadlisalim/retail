<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /** "Forgot password" form. */
    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    /** Email a reset link. */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        // Avoid leaking which emails exist: always show the same friendly message.
        return back()->with('status', __($status));
    }

    /** Reset form (from the emailed link). */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    /** Persist the new password. */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PasswordReset) {
            return redirect()->route('login')->with('success', 'Kata sandi berhasil diubah. Silakan masuk.');
        }

        // Invalid/expired link (e.g. email arrived after expiry, or an older link
        // was used) — send them back to request a fresh link with a clear message
        // instead of a dead-end error on the reset form.
        if (in_array($status, [Password::INVALID_TOKEN, Password::INVALID_USER], true)) {
            return redirect()->route('password.request')
                ->withInput($request->only('email'))
                ->with('status', 'Tautan atur ulang kata sandi tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru di bawah ini.');
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
