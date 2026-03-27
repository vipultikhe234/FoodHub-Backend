<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_variant_id',
        'quantity',
    ];

    /**
     * Get the user who owns the cart item.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the variant in the cart.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Calculate subtotal for the cart item.
     */
    public function getSubtotalAttribute()
    {
        return $this->variant ? $this->variant->price * $this->quantity : 0;
    }
}
