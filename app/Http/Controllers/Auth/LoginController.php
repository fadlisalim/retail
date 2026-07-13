<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, CartService $cart, WishlistService $wishlist): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guestToken = $request->session()->get('guest_token');
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials + ['is_active' => true], $remember)) {
            LoginActivity::create([
                'email' => $credentials['email'], 'successful' => false,
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi salah, atau akun tidak aktif.',
            ]);
        }

        $request->session()->regenerate();
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        LoginActivity::create([
            'user_id' => $user->id, 'email' => $user->email, 'successful' => true,
            'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'created_at' => now(),
        ]);

        // Merge anything collected as a guest into the account.
        if ($guestToken) {
            $cart->mergeGuestIntoUser($user->id, $guestToken);
            $wishlist->mergeGuestIntoUser($user->id, $guestToken);
        }

        $target = $user->isStaffMember() ? route('admin.dashboard') : route('account.dashboard');

        return redirect()->intended($target);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Anda telah keluar.');
    }
}
