<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasMerchantFilter
{
    /**
     * Scope a query to only include records belonging to a specific merchant.
     */
    public function scopeByMerchant(Builder $query, $merchantId = null): Builder
    {
        $user = Auth::user();

        // 1. If explicit merchantId provided (Admin choosing from dropdown)
        if ($merchantId) {
            return $query->where('merchant_id', $merchantId);
        }

        // 2. If logged in as Merchant, auto-filter by their own profile
        if ($user && $user->role === 'merchant') {
            $myMerchant = $user->merchant;
            if ($myMerchant) {
                return $query->where('merchant_id', $myMerchant->id);
            }
            // If merchant has no linked profile, show nothing for safety
            return $query->where('merchant_id', 0);
        }

        // 3. For Admin (without explicit ID) or Customers/Guests, show all
        return $query;
    }
}
