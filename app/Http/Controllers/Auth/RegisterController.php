<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, CartService $cart, WishlistService $wishlist): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            // Only *active* accounts block a new sign-up. A soft-deleted account with
            // the same email is revived below (its row still occupies the unique index).
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'whatsapp' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau reset kata sandi.',
        ]);

        $guestToken = $request->session()->get('guest_token');

        $user = DB::transaction(function () use ($data) {
            // Revive a previously-deleted account using this email — the DB unique
            // index would otherwise reject a fresh insert.
            $user = User::onlyTrashed()->where('email', $data['email'])->first();

            if ($user) {
                $user->restore();
                $user->forceFill([
                    'name' => $data['name'],
                    'whatsapp' => $data['whatsapp'],
                    'phone' => $data['whatsapp'],
                    'password' => $data['password'],
                    'is_active' => true,
                    'email_verified_at' => null,
                ])->save();
            } else {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'whatsapp' => $data['whatsapp'],
                    'phone' => $data['whatsapp'],
                    'password' => $data['password'],
                    'is_active' => true,
                ]);
            }

            CustomerProfile::firstOrCreate(
                ['user_id' => $user->id],
                ['customer_type' => 'personal'],
            );

            return $user;
        });

        // Fires SendEmailVerificationNotification (User implements MustVerifyEmail).
        event(new Registered($user));

        // Merge guest cart/wishlist into the account (by id — no login needed).
        if ($guestToken) {
            $cart->mergeGuestIntoUser($user->id, $guestToken);
            $wishlist->mergeGuestIntoUser($user->id, $guestToken);
        }

        // Verification is required BEFORE login — do not auto-login. Send them to
        // the login page with a prompt to verify via the email we just sent.
        return redirect()->route('login')
            ->with('success', 'Akun berhasil dibuat. Kami telah mengirim tautan verifikasi ke '.$user->email.'. Silakan verifikasi email Anda, lalu masuk.');
    }
}
