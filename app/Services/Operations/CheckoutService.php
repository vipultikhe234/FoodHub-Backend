<?php

namespace App\Services\Operations;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use App\Models\Merchant;
use App\Models\UserAddress;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CheckoutService
{
    public function __construct(
        protected \App\Services\Identity\FCMService $fcmService
    ) {}

    /**
     * Complete the checkout process in a single atomic transaction.
     * Follows the 'ACID Path' with stock reservation and data snapshotting.
     */
    public function process(array $data, $user)
    {
        return DB::transaction(function () use ($data, $user) {
            // 1. Resolve Global Entities
            $Merchant = Merchant::with('user')->findOrFail($data['merchant_id']);
            $address = UserAddress::find($data['address_id']);
            
            // 2. Initial Calculations
            $subtotal = 0;
            
            // 3. Process Items & Resolve Stock
            foreach ($data['items'] as $item) {
                $itemSubtotal = $item['unit_price'] * $item['quantity'];
                $subtotal += $itemSubtotal;

                // Resolve Stock Context (Inventory Table vs Product Master)
                $inventory = Inventory::where('product_variant_id', $item['product_variant_id'])
                    ->where('merchant_id', $Merchant->id)
                    ->lockForUpdate()
                    ->first();

                if ($inventory) {
                    if (($inventory->stock - $inventory->reserved_stock) < $item['quantity']) {
                        throw new \Exception("Item '{$item['product_name']}' is out of stock in required quantity.");
                    }
                    $inventory->increment('reserved_stock', $item['quantity']);
                } else {
                    $product = \App\Models\Product::find($item['product_id']);
                    if (!$product || $product->stock < $item['quantity']) {
                        throw new \Exception("Item '{$item['product_name']}' is currently out of stock.");
                    }
                    $product->decrement('stock', $item['quantity']);
                }
            }

            // 4. Resolve Fees & Discounts
            $deliveryFee = $data['delivery_fee'] ?? 0;
            $packingCharge = $data['packing_charge'] ?? 0;
            $platformFee = $data['platform_fee'] ?? 0;
            $taxAmount = $data['tax_amount'] ?? ($subtotal * 0.05); // Default 5% GST
            
            $discountAmount = 0;
            if (isset($data['coupon_code'])) {
                $coupon = Coupon::where('code', $data['coupon_code'])
                    ->where(function($q) use ($Merchant) {
                        $q->whereNull('merchant_id')
                          ->orWhere('merchant_id', $Merchant->id);
                    })
                    ->active()
                    ->first();

                if ($coupon && $subtotal >= (float)$coupon->min_order_amount) {
                    $discountAmount = $coupon->type === 'percentage' 
                        ? ($subtotal * (float)$coupon->value / 100) 
                        : (float)$coupon->value;
                }
            }

            $totalAmount = ($subtotal + $deliveryFee + $packingCharge + $platformFee + $taxAmount) - $discountAmount;

            // Generate Unique Order Number (3 random chars + 7 random digits)
            do {
                $orderNumber = strtoupper(Str::random(3)) . rand(1000000, 9999999);
            } while (Order::where('order_number', $orderNumber)->exists());

            // 5. Create Order Header
            $order = Order::create([
                'order_number'     => $orderNumber,
                'idempotency_key'  => $data['idempotency_key'] ?? null,
                'user_id'          => $user->id,
                'merchant_id'      => $Merchant->id,
                'subtotal'         => $subtotal,
                'tax_amount'       => $taxAmount,
                'delivery_fee'     => $deliveryFee,
                'packaging_fee'    => $packingCharge,
                'platform_fee'     => $platformFee,
                'coupon_discount'  => $discountAmount,
                'coupon_code'      => $data['coupon_code'] ?? null,
                'total_price'      => $totalAmount,
                'address_id'       => $address?->id,
                'payment_method'   => $data['payment_method'] ?? 'COD',
                'payment_status'   => 'pending',
                'status'           => 'placed',
                'order_type'       => $address ? 'delivery' : 'pickup',
                'notes'            => $data['notes'] ?? null,
            ]);

            // 6. Bulk Create Items - only using DB columns
            foreach ($data['items'] as $item) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'        => $item['product_id'],
                    'product_variant_id'=> $item['product_variant_id'],
                    'quantity'          => $item['quantity'],
                    'price'             => $item['unit_price'],
                    'total'             => $item['unit_price'] * $item['quantity'],
                ]);
            }

            // 7. Initialize Log
            $order->logs()->create([
                'status' => 'placed',
                'notes' => 'Order placed successfully. Awaiting merchant acceptance.',
                'changed_by_type' => 'user',
                'changed_by_id' => $user->id
            ]);

            // 8. Real-time Merchant Notification
            if ($Merchant->user && $Merchant->user->fcm_token) {
                $this->fcmService->sendNotification(
                    $Merchant->user->fcm_token,
                    '🚀 New Order!',
                    "New order #{$order->order_number} received from {$user->name}",
                    ['type' => 'new_order', 'order_id' => (string)$order->id],
                    $Merchant->user->id
                );
            }

            return $order;
        });
    }
}

