<?php

namespace App\Services\Analytics;

use App\Models\Order;

class SettlementService
{
    /**
     * Calculate the settlement breakdown for a single order.
     * Uses the unified logic refined in high-precision reporting.
     *
     * @param Order $order
     * @return array
     */
    public function calculate(Order $order): array
    {
        $isMerchantCoupon = $order->coupon && !$order->coupon->is_admin_coupon;
        $isAdminCoupon = $order->coupon && $order->coupon->is_admin_coupon;

        $otherCharges = $order->merchant->other_charges ?? null;
        $commissionRate = $otherCharges->commission_rate ?? 0;

        // 1. Decoupled Tax Components
        $i_tax = (float)($order->items_tax ?? 0);
        $p_tax = (float)($order->packaging_tax ?? 0);
        $d_tax = (float)($order->delivery_tax ?? 0);
        $pl_tax = (float)($order->platform_tax ?? 0);

        // 2. Base Payout Logic
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

        // Final Merchant Payout Formula:
        // (Items Subtotal - Coupon + Reimbursement) + (Merchant Taxes) + (Merchant Fees) - (Platform Commission)
        $merchantPayout = $baseSubtotal + $i_tax + $p_tax + $d_tax + (float) $order->packaging_fee + (float) $order->delivery_fee + $merchantAdjustment - $merchantCommission;

        // 3. Platform Calculations (Fee + Platform Tax + Commission)
        $adminProfit = (float) $order->platform_fee + $pl_tax + $merchantCommission;
        if ($isAdminCoupon) {
            $adminProfit -= (float) $order->coupon_discount;
        }

        return [
            'merchant_payout'     => round($merchantPayout, 2),
            'admin_profit'        => round($adminProfit, 2),
            'merchant_commission' => round($merchantCommission, 2),
            'commission_rate'     => $commissionRate . '%',
            'base_subtotal'       => round((float)($order->subtotal), 2),
            'items_gst'           => round($i_tax, 2),
            'packaging_fee'       => round((float)($order->packaging_fee ?? 0), 2),
            'packaging_tax'       => round($p_tax, 2),
            'delivery_fee'        => round((float)($order->delivery_fee ?? 0), 2),
            'delivery_tax'        => round($d_tax, 2),
            'platform_fee'        => round((float)($order->platform_fee ?? 0), 2),
            'platform_tax'        => round($pl_tax, 2),
            'platform_total'      => round((float)($order->platform_fee ?? 0) + $pl_tax, 2),
            'items_gst_percent'   => floatval($order->subtotal ?? 1) > 0 ? round(($i_tax / floatval($order->subtotal ?? 1)) * 100, 1) : 0,
            'merchant_adjustment' => round($merchantAdjustment ?? 0, 2),
            'tax_breakdown'       => [
                'items_gst'     => round($i_tax, 2),
                'packaging_gst' => round($p_tax, 2),
                'delivery_gst'  => round($d_tax, 2),
                'platform_gst'  => round($pl_tax, 2),
            ],
            'coupon_type'         => $order->coupon ? ($order->coupon->is_admin_coupon ? 'Platform' : 'Merchant') : 'None',
            'is_merchant_coupon'  => $isMerchantCoupon,
            'is_admin_coupon'     => $isAdminCoupon
        ];
    }
}
