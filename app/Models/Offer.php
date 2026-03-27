<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'product_id',
        'title',
        'description',
        'type',
        'image',
        'banner_url',
        'value',
        'discount_type',
        'discount_value',
        'priority',
        'usage_count',
        'starts_at',
        'expires_at',
        'start_date',
        'end_date',
        'is_active'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'priority' => 'integer',
        'usage_count' => 'integer'
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope a query to only include active offers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
