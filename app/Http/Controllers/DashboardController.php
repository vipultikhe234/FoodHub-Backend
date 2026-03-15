<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats()
    {
        $totalOrders   = Order::count();

        // Count revenue from both paid & delivered orders (not just delivered)
        $totalRevenue  = Order::whereIn('payment_status', ['paid'])
            ->sum('total_price');

        $totalDiscounts = Order::sum('discount');

        $totalUsers    = User::where('role', 'customer')->count();
        $totalProducts = Product::count();

        // Orders in the last 7 days
        $recentOrders  = Order::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        // Real 7-day sales trend
        $salesTrend = Order::selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Order status summary
        $statusSummary = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total_orders'        => $totalOrders,
            'total_revenue'       => (float) $totalRevenue,
            'total_discounts'     => (float) $totalDiscounts,
            'total_users'         => $totalUsers,
            'total_products'      => $totalProducts,
            'recent_orders_count' => $recentOrders,
            'sales_trend'         => $salesTrend,
            'status_summary'      => $statusSummary,
        ]);
    }
}
