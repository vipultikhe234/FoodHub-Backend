<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Merchant;
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
            $MerchantId = $user->Merchant?->id;
            if (!$MerchantId) return response()->json(['message' => 'No Merchant context'], 404);
            $targetId = $MerchantId;
        }

        if ($targetId) {
            // MERCHANT or ADMIN FILTERED STATS
            $totalOrders   = Order::byMerchant($targetId)->count();
            $totalRevenue  = Order::byMerchant($targetId)->whereIn('payment_status', ['paid'])->sum('total_price');
            $totalProducts = Product::byMerchant($targetId)->count();
            $recentOrders  = Order::byMerchant($targetId)->where('created_at', '>=', Carbon::now()->subDays(7))->count();

            $salesTrend = Order::byMerchant($targetId)
                ->selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date', 'asc')
                ->get();

            $statusSummary = Order::byMerchant($targetId)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');

            $Merchant = Merchant::find($targetId);

            return response()->json([
                'context'             => $Merchant ? $Merchant->name : 'Merchant Node',
                'total_orders'        => $totalOrders,
                'total_revenue'       => (float) $totalRevenue,
                'total_products'      => $totalProducts,
                'recent_orders_count' => $recentOrders,
                'sales_trend'         => $salesTrend,
                'status_summary'      => $statusSummary,
            ]);
        }

        // GLOBAL ADMIN STATS (Only when Admin and NO merchant_id filter)
        $totalOrders   = Order::count();
        $totalRevenue  = Order::whereIn('payment_status', ['paid'])->sum('total_price');
        $totalDiscounts = Order::sum('coupon_discount');
        $totalUsers    = User::where('role', 'customer')->count();
        $totalProducts = Product::count();
        $totalMerchants = Merchant::count();
        $recentOrders  = Order::where('created_at', '>=', Carbon::now()->subDays(7))->count();

        $salesTrend = Order::selectRaw('DATE(created_at) as date, SUM(total_price) as total, COUNT(*) as count')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $statusSummary = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'context'             => 'Global Enterprise',
            'total_orders'        => $totalOrders,
            'total_revenue'       => (float) $totalRevenue,
            'total_discounts'     => (float) $totalDiscounts,
            'total_users'         => $totalUsers,
            'total_products'      => $totalProducts,
            'total_Merchants'   => $totalMerchants,
            'recent_orders_count' => $recentOrders,
            'sales_trend'         => $salesTrend,
            'status_summary'      => $statusSummary,
        ]);
    }
}

