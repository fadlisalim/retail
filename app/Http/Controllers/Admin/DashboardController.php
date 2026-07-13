<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $totalRevenue = (float) Order::where('payment_status', 'paid')->sum('grand_total');
        $orderCount = Order::count();
        $unpaidOrders = Order::where('payment_status', 'unpaid')->count();
        $toProcess = Order::whereIn('status', ['payment_verified', 'processing', 'packing'])->count();

        $lowStock = Product::whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0)->count();
        $outOfStock = Product::where('stock', '<=', 0)->count();

        $pendingQuotations = Quotation::whereIn('status', ['new', 'under_review'])->count();
        $pendingReviews = ReviewReport::where('resolved', false)->count();
        $newCustomers = User::where('is_staff', false)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $recentOrders = Order::with('user')->latest()->take(8)->get();
        $topProducts = Product::orderByDesc('sold_count')->take(5)->get();

        $ordersByStatus = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Sales chart: revenue for the last 14 days (built in PHP so it stays
        // database-agnostic and avoids date-function differences).
        $since = now()->subDays(13)->startOfDay();
        $paidOrders = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $since)
            ->get(['grand_total', 'created_at']);

        $salesChart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $value = $paidOrders
                ->filter(fn (Order $o) => $o->created_at?->isSameDay($day))
                ->sum(fn (Order $o) => (float) $o->grand_total);
            $salesChart[] = ['label' => $day->format('d/m'), 'value' => (float) $value];
        }

        return view('admin.dashboard', compact(
            'totalRevenue', 'orderCount', 'unpaidOrders', 'toProcess',
            'lowStock', 'outOfStock', 'pendingQuotations', 'pendingReviews', 'newCustomers',
            'recentOrders', 'topProducts', 'ordersByStatus', 'salesChart',
        ));
    }
}
