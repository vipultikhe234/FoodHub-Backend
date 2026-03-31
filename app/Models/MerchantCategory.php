<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantCategory extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    public function merchants(): HasMany
    {
        return $this->hasMany(Merchant::class, 'merchant_category_id');
    }
}
