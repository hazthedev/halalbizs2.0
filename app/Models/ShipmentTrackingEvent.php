<?php

namespace App\Models;

use App\Enums\ShipmentTrackingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentTrackingEvent extends Model
{
    protected $fillable = [
        'sub_order_id',
        'provider',
        'external_id',
        'status',
        'raw_status',
        'message',
        'location',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentTrackingStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }
}
