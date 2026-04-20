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

        $processedOrders = $orders->map(function ($order) use (&$totalMerchantPayout, &$totalAdminProfit) {
            $isMerchantCoupon = $order->coupon && !$order->coupon->is_admin_coupon;
            $isAdminCoupon = $order->coupon && $order->coupon->is_admin_coupon;

            $otherCharges = $order->merchant->other_charges;

            // 1. Merchant Calculations (Inbound)
            // Assuming fees in DB are inclusive of GST. We decouple them for display.
            $p_rate = (float) ($otherCharges->packaging_charge_tax ?? 0);
            $d_rate = (float) ($otherCharges->delivery_charge_tax ?? 0);

            $packagingBase = (float) $order->packaging_fee / (1 + ($p_rate / 100));
            $packagingGst = (float) $order->packaging_fee - $packagingBase;

            $deliveryBase = (float) $order->delivery_fee / (1 + ($d_rate / 100));
            $deliveryGst = (float) $order->delivery_fee - $deliveryBase;

            $commissionRate = $otherCharges->commission_rate ?? 0;
            $merchantCommission = ($order->subtotal * ($commissionRate / 100));

            $baseSubtotal = (float) $order->subtotal;
            $merchantAdjustment = 0;

            if ($isMerchantCoupon) {
                // Merchant funded: Deduct from their total
                $merchantAdjustment = -((float) $order->coupon_discount);
            } elseif ($isAdminCoupon) {
                // Admin funded: Merchant sees discounted price + reimbursement
                $baseSubtotal -= (float) $order->coupon_discount;
                $merchantAdjustment = (float) $order->coupon_discount;
            }

            $merchantPayout = $baseSubtotal + (float) $order->tax_amount + (float) $order->packaging_fee + (float) $order->delivery_fee + $merchantAdjustment - $merchantCommission;

            // 2. Platform Calculations (Inbound)
            $pl_rate = (float) ($otherCharges->platform_fee_tax ?? 0);
            $platformBase = (float) $order->platform_fee / (1 + ($pl_rate / 100));
            $platformGst = (float) $order->platform_fee - $platformBase;

            $adminProfit = (float) $order->platform_fee + $merchantCommission;
            if ($isAdminCoupon) {
                $adminProfit -= (float) $order->coupon_discount;
            }

            $totalMerchantPayout += $merchantPayout;
            $totalAdminProfit += $adminProfit;

            // Final calculation summary
            return array_merge($order->toArray(), [
                'calculations' => [
                    'merchant_payout' => round($merchantPayout, 2),
                    'admin_profit' => round($adminProfit, 2),
                    'merchant_commission' => round($merchantCommission, 2),
                    'commission_rate' => $commissionRate . '%',
                    'base_subtotal' => round((float)($order->subtotal ?? $order->sub_total), 2),
                    'items_gst' => round((float)($order->items_tax ?? 0), 2),
                    'packaging_fee' => round((float)($order->packaging_fee ?? 0), 2),
                    'packaging_tax' => round((float)($order->packaging_tax ?? 0), 2),
                    'delivery_fee' => round((float)($order->delivery_fee ?? 0), 2),
                    'delivery_tax' => round((float)($order->delivery_tax ?? 0), 2),
                    'platform_fee' => round((float)($order->platform_fee ?? 0), 2),
                    'platform_tax' => round((float)($order->platform_tax ?? 0), 2),
                    'items_gst_percent' => floatval($order->subtotal ?? 1) > 0 ? round((floatval($order->items_tax ?? 0) / floatval($order->subtotal ?? 1)) * 100, 1) : 0,
                    'merchant_adjustment' => round($merchantAdjustment ?? 0, 2),
                    'tax_breakdown' => [
                        'items_gst' => round((float)($order->items_tax ?? $order->tax_amount), 2),
                        'packaging_gst' => round((float)($order->packaging_tax ?? 0), 2),
                        'delivery_gst' => round((float)($order->delivery_tax ?? 0), 2),
                        'platform_gst' => round((float)($order->platform_tax ?? 0), 2),
                    ],
                    'coupon_type' => $order->coupon ? ($order->coupon->is_admin_coupon ? 'Platform' : 'Merchant') : 'None',
                    'is_merchant_coupon' => $isMerchantCoupon,
                    'is_admin_coupon' => $isAdminCoupon
                ]
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
