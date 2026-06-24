<?php

namespace App\Models;

use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One return request per sub-order (docs/09 §D). The sub-order status is
 * the fulfilment truth; this row tracks the dispute/refund paperwork.
 */
class ReturnRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** App-enforced ceiling for the buyer's evidence photos. */
    public const MAX_PHOTOS = 5;

    protected $fillable = [
        'sub_order_id', 'return_reason_id', 'description', 'status', 'refund_sen',
        'seller_response', 'respond_by', 'escalated_at', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'refund_sen' => 'integer',
            'respond_by' => 'datetime',
            'escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'return_reason_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
