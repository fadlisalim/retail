<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $guestToken = $request->session()->get('guest_token');

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'whatsapp' => $data['whatsapp'],
                'phone' => $data['whatsapp'],
                'password' => $data['password'],
                'is_active' => true,
            ]);

            CustomerProfile::create(['user_id' => $user->id, 'customer_type' => 'personal']);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        if ($guestToken) {
            $cart->mergeGuestIntoUser($user->id, $guestToken);
            $wishlist->mergeGuestIntoUser($user->id, $guestToken);
        }

        return redirect()->route('account.dashboard')->with('success', 'Akun berhasil dibuat. Selamat datang di '.brand().'!');
    }
}
