<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $fillable = [
        'product_variant_id',
        'merchant_id',
        'stock',
        'reserved_stock',
        'is_available',
    ];

    protected $casts = [
        'stock' => 'integer',
        'reserved_stock' => 'integer',
        'is_available' => 'boolean',
    ];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                     ->whereRaw('stock - reserved_stock > 0');
    }
}
