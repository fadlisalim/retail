<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(): View
    {
        return view('account.orders.index', [
            'orders' => auth()->user()->orders()->with('items')->latest()->paginate(10),
        ]);
    }

    public function show(Order $order): View
    {
        abort_unless($order->user_id === auth()->id(), 403);
        $order->load(['items.product', 'shippingAddress', 'statusHistories.changedBy', 'payments', 'invoice']);

        return view('account.orders.show', ['order' => $order]);
    }
}
