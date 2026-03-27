<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasMerchantFilter;

class Order extends Model
{
    use HasFactory, HasMerchantFilter;

    // Final Status Flow (Production Grade)
    const STATUS_PLACED = 'placed';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';

    const TYPE_DELIVERY = 'delivery';
    const TYPE_PICKUP = 'pickup';

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'user_id',
        'merchant_id',
        'rider_id',
        'coupon_id',
        'total_price',
        'subtotal',
        'tax_amount',
        'delivery_fee',
        'packaging_fee',
        'platform_fee',
        'coupon_discount',
        'coupon_code',
        'address_id',
        'address_snapshot', // snapshot
        'payment_method',
        'status',
        'payment_status',
        'order_type',
        'notes',
        'cancellation_reason',
        'estimated_delivery_time',
        'actual_delivery_time',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'packaging_fee' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'coupon_discount' => 'decimal:2',
        'estimated_delivery_time' => 'datetime',
        'actual_delivery_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function rider()
    {
        return $this->belongsTo(User::class, 'rider_id');
    }

    /**
     * Scope for active orders (not delivered or cancelled).
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_FAILED]);
    }
}
