<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()
            ->where('is_staff', false)
            ->with('profile')
            ->withCount('orders');

        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(function ($sub) use ($term) {
                $sub->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('whatsapp', 'like', "%{$term}%");
            });
        }

        $customers = $query->latest()->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(User $user): View
    {
        abort_if($user->is_staff, 404);

        $user->load([
            'profile',
            'addresses',
            'orders' => fn ($q) => $q->latest(),
            'quotations' => fn ($q) => $q->latest(),
            'reviews.product',
        ]);

        return view('admin.customers.show', compact('user'));
    }
}
