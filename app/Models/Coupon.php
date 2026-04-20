<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Traits\HasMerchantFilter;

class Coupon extends Model
{
    use HasFactory, HasMerchantFilter;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_discount',
        'merchant_id',
        'is_active',
        'expires_at',
        'show_on_landing',
        'is_admin_coupon',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'is_active' => 'boolean',
        'show_on_landing' => 'boolean',
        'is_admin_coupon' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * Override HasMerchantFilter's scopeByMerchant to ALSO include platform-wide (Admin) coupons.
     * Admin coupons should be shown to every user regardless of the merchant.
     */
    public function scopeByMerchant($query, $merchantId = null)
    {
        // 1. If explicit merchantId provided (Customer in Cart)
        if ($merchantId) {
            return $query->where(function($q) use ($merchantId) {
                $q->where('merchant_id', $merchantId)
                  ->orWhere('is_admin_coupon', true);
            });
        }

        // 2. If logged in as Merchant, auto-filter by their own profile
        $user = \Illuminate\Support\Facades\Auth::user();
        if ($user && $user->role === 'merchant') {
            $myMerchant = $user->merchant;
            if ($myMerchant) {
                return $query->where('merchant_id', $myMerchant->id);
            }
            return $query->where('merchant_id', 0);
        }

        // 3. For Admin overview, show all
        return $query;
    }

    /**
     * Scope to only include active and non-expired coupons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', Carbon::now());
                     });
    }
}
