<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of a return: a specific order item, quantity and computed refund (sen). */
class ReturnRequestItem extends Model
{
    protected $fillable = ['return_request_id', 'order_item_id', 'qty', 'refund_sen'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'refund_sen' => 'integer',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
