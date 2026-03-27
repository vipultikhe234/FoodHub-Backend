<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasMerchantFilter;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasMerchantFilter;

    protected $fillable = [
        'merchant_id',
        'category_id',
        'name',
        'description',
        'price',
        'discount_price',
        'image',
        'stock',
        'is_veg',
        'spicy_level',
        'calories',
        'preparation_time',
        'is_popular',
        'is_recommended',
        'is_new',
        'tax_rate',
        'has_variants',
        'is_active',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_veg' => 'boolean',
        'is_popular' => 'boolean',
        'is_recommended' => 'boolean',
        'is_new' => 'boolean',
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'product_id'); 
    }


    /**
     * Scope a query to only include active products.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
