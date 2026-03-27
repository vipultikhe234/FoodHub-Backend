<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasMerchantFilter;

class Category extends Model
{
    use HasFactory, HasMerchantFilter;

    protected $fillable = ['name', 'image', 'status', 'merchant_id'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
