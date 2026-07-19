<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('account.profile', ['user' => auth()->user()->load('profile')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:30'],
        ]);

        // Normalise to international format (08… → 62…) for WhatsApp delivery.
        $wa = app(\App\Services\WhatsAppService::class);
        $whatsapp = $wa->normalize($data['whatsapp']) ?? $data['whatsapp'];

        $user->update([
            'name' => $data['name'],
            'whatsapp' => $whatsapp,
            'phone' => ($data['phone'] ?? null) ? ($wa->normalize($data['phone']) ?? $data['phone']) : $whatsapp,
        ]);

        $user->profile()->updateOrCreate([], [
            'company_name' => $data['company_name'] ?? null,
            'npwp' => $data['npwp'] ?? null,
        ]);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}
