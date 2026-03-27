<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantOtherCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'delivery_charge',
        'packaging_charge',
        'platform_fee',
        'delivery_charge_tax',
        'packaging_charge_tax',
        'platform_fee_tax',
    ];

    protected $casts = [
        'delivery_charge' => 'decimal:2',
        'packaging_charge' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'delivery_charge_tax' => 'decimal:2',
        'packaging_charge_tax' => 'decimal:2',
        'platform_fee_tax' => 'decimal:2',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
