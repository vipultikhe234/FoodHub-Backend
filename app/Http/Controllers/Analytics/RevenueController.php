<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Merchant;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function revenueReport(Request $request)
    {
        $user = $request->user();
        $merchantId = $request->query('merchant_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $settlementService = app(\App\Services\Analytics\SettlementService::class);

        // Logic branching: if Merchant, ignore merchant_id and use their own.
        if ($user->role === 'merchant') {
            $merchant = $user->merchant;
            if (!$merchant) {
                return response()->json(['message' => 'No Merchant context'], 404);
            }
            $merchantId = $merchant->id;
        }

        // Default to last week if no dates provided or valid
        try {
            if (!$startDate || $startDate === 'undefined' || $startDate === 'null') {
                $startDate = Carbon::now()->subWeek()->startOfDay()->toDateTimeString();
            } else {
                $startDate = Carbon::parse($startDate)->toDateTimeString();
            }

            if (!$endDate || $endDate === 'undefined' || $endDate === 'null') {
                $endDate = Carbon::now()->endOfDay()->toDateTimeString();
            } else {
                $endDate = Carbon::parse($endDate)->toDateTimeString();
            }
        } catch (\Exception $e) {
            $startDate = Carbon::now()->subWeek()->startOfDay()->toDateTimeString();
            $endDate = Carbon::now()->endOfDay()->toDateTimeString();
        }

        $query = Order::with(['items.product', 'items.variant', 'coupon', 'merchant.other_charges'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['delivered', 'picked_up', 'ready', 'preparing', 'accepted', 'out_for_delivery'])
            ->where('payment_status', 'paid');

        if ($merchantId && $merchantId !== 'undefined' && $merchantId !== 'null' && $merchantId !== '') {
            $query->where('merchant_id', $merchantId);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $totalOrders = $orders->count();
        $totalGMV = $orders->sum('total_price');

        // Calculated Metrics
        $totalMerchantPayout = 0;
        $totalAdminProfit = 0;

        $processedOrders = $orders->map(function ($order) use (&$totalMerchantPayout, &$totalAdminProfit, $settlementService) {
            $calculations = $settlementService->calculate($order);

            $totalMerchantPayout += $calculations['merchant_payout'];
            $totalAdminProfit += $calculations['admin_profit'];

            return array_merge($order->toArray(), [
                'calculations' => $calculations
            ]);
        });

        return response()->json([
            'summary' => [
                'total_orders' => $totalOrders,
                'total_revenue' => (float) round($totalGMV, 2),
                'total_merchant_payout' => (float) round($totalMerchantPayout, 2),
                'total_admin_profit' => (float) round($totalAdminProfit, 2),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'merchant_id' => $merchantId
            ],
            'orders' => $processedOrders,
            'role' => $user->role
        ]);
    }
}
