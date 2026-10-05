<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\WaCampaign\WaContactService;
use App\Services\WhatsAppService;
use App\Services\WishlistService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'promo_consent' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau reset kata sandi.',
        ]);

        // Store WhatsApp in international format (08… → 62…) so Wablas can reach it.
        $data['whatsapp'] = app(WhatsAppService::class)->normalize($data['whatsapp']) ?? $data['whatsapp'];

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

        // Izin promo WhatsApp (centang saat daftar) → kontak WA Campaign dengan bukti.
        if ($request->boolean('promo_consent')) {
            app(WaContactService::class)->recordConsent(
                $user->whatsapp, $user->name, $user->id, 'register',
                'Centang "bersedia menerima promo via WhatsApp" saat daftar akun pada '.now()->format('d/m/Y H:i').' (IP '.substr(hash('sha256', (string) $request->ip()), 0, 12).')',
            );
        }

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
