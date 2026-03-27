<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'notes',
        'changed_by_type',
        'changed_by_id',
    ];

    /**
     * Get the order that owns the log.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the actor who changed the status.
     * This can be a User (Customer/Admin/Rider).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
