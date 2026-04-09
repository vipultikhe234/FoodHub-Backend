<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingOffer extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'link',
        'type',
        'source_id',
        'merchant_id',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
