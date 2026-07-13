<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        return view('account.dashboard', [
            'user' => $user,
            'recentOrders' => $user->orders()->latest()->take(5)->get(),
            'orderCount' => $user->orders()->count(),
            'quotationCount' => $user->quotations()->count(),
            'reviewCount' => $user->reviews()->count(),
            'unreadNotifications' => $user->unreadNotifications()->count(),
        ]);
    }
}
