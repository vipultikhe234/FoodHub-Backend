<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Review;
use App\Models\Offer;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function stats(Request $request)
    {
        $user = $request->user();
        $targetId = $request->query('merchant_id');

        // Logic branching: if Merchant, ignore targetId and use their own. If Admin, use optional targetId.
        if ($user->role === 'merchant') {
            $merchant = $user->merchant;
            if (!$merchant) return response()->json(['message' => 'No Merchant context'], 404);
            $targetId = $merchant->id;
        }

        $now = Carbon::now();
        $todayStart = Carbon::today();

        if ($targetId) {
            // --- MERCHANT SPECIFIC STATS ---
            $merchantQuery = function($q) use ($targetId) {
                return $q->where('merchant_id', $targetId);
            };

            $totalOrders = Order::where('merchant_id', $targetId)->count();
            $todayOrders = Order::where('merchant_id', $targetId)->where('created_at', '>=', $todayStart)->count();
            $pendingOrders = Order::where('merchant_id', $targetId)->whereIn('status', ['placed', 'pending'])->count();
            $completedOrders = Order::where('merchant_id', $targetId)->where('status', 'delivered')->count();
            
            $totalRevenue = Order::where('merchant_id', $targetId)->whereIn('payment_status', ['paid'])->sum('total_price');
            $todayRevenue = Order::where('merchant_id', $targetId)->whereIn('payment_status', ['paid'])->where('created_at', '>=', $todayStart)->sum('total_price');
            
            $activeOrders = Order::where('merchant_id', $targetId)->whereIn('status', ['confirmed', 'preparing', 'dispatched'])->count();
            $preparingOutForDelivery = Order::where('merchant_id', $targetId)->whereIn('status', ['preparing', 'dispatched'])->count();
            
            $totalProducts = Product::where('merchant_id', $targetId)->count();
            $lowStockProducts = Product::where('merchant_id', $targetId)->where('stock', '<=', 5)->count();
            
            $uniqueCustomers = Order::where('merchant_id', $targetId)->distinct('user_id')->count('user_id');
            $avgRating = Review::where('merchant_id', $targetId)->avg('rating') ?: 0;
            
            $activeOffersCount = Offer::where('merchant_id', $targetId)
                ->where('is_active', true)
                ->where(function($q) use ($now) {
                    $q->whereNull('end_date')->orWhere('end_date', '>=', $now->startOfDay());
                })->count();

            // Trends (7 days)
            $salesTrend = Order::where('merchant_id', $targetId)
                ->selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
                ->where('created_at', '>=', $now->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            return response()->json([
                'context'             => 'Merchant',
                'total_orders'        => $totalOrders,
                'today_orders'        => $todayOrders,
                'pending_orders'      => $pendingOrders,
                'completed_orders'    => $completedOrders,
                'total_revenue'       => (float) $totalRevenue,
                'today_revenue'       => (float) $todayRevenue,
                'active_orders'       => $activeOrders,
                'preparing_out_for_delivery' => $preparingOutForDelivery,
                'total_products'      => $totalProducts,
                'low_stock_products'  => $lowStockProducts,
                'total_customers'     => $uniqueCustomers,
                'avg_rating'          => round($avgRating, 1),
                'active_offers'       => $activeOffersCount,
                'sales_trend'         => $salesTrend,
            ]);
        }

        // --- GLOBAL ADMIN STATS ---
        $totalOrders = Order::count();
        $todayOrders = Order::where('created_at', '>=', $todayStart)->count();
        $pendingOrders = Order::whereIn('status', ['placed', 'pending'])->count();
        $completedOrders = Order::where('status', 'delivered')->count();
        
        $totalRevenue = Order::whereIn('payment_status', ['paid'])->sum('total_price');
        $todayRevenue = Order::whereIn('payment_status', ['paid'])->where('created_at', '>=', $todayStart)->sum('total_price');
        
        $activeOrders = Order::whereIn('status', ['confirmed', 'preparing', 'dispatched'])->count();
        $outForDelivery = Order::where('status', 'dispatched')->count();
        
        $totalUsers = User::where('role', 'customer')->count();
        $todayNewUsers = User::where('role', 'customer')->where('created_at', '>=', $todayStart)->count();
        
        $totalMerchants = Merchant::count();
        $activeMerchantsCount = Merchant::where('is_active', true)->count();
        
        $activeRiders = User::where('role', 'rider')->where('is_ready', true)->count();
        $lowStockProducts = Product::where('stock', '<=', 5)->count();
        $failedOrders = Order::whereIn('payment_status', ['failed', 'cancelled'])->count();

        // Global Trend
        $salesTrend = Order::selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
            ->where('created_at', '>=', $now->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'context'             => 'Global',
            'total_orders'        => $totalOrders,
            'today_orders'        => $todayOrders,
            'pending_orders'      => $pendingOrders,
            'completed_orders'    => $completedOrders,
            'total_revenue'       => (float) $totalRevenue,
            'today_revenue'       => (float) $todayRevenue,
            'active_orders'       => $activeOrders,
            'out_for_delivery'    => $outForDelivery,
            'total_users'         => $totalUsers,
            'today_new_users'     => $todayNewUsers,
            'total_merchants'      => $totalMerchants,
            'active_merchants'    => $activeMerchantsCount,
            'active_riders'       => $activeRiders,
            'low_stock_products'  => $lowStockProducts,
            'failed_orders'       => $failedOrders,
            'sales_trend'         => $salesTrend,
        ]);
    }
}

