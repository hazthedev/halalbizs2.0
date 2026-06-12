<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Enums\LedgerEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * amount_sen is SIGNED: credits positive, debits negative.
 * Balance = SUM(amount_sen) WHERE status='available'.
 */
class StoreLedgerEntry extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['store_id', 'sub_order_id', 'payout_id', 'type', 'amount_sen', 'status', 'description'];

    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'status' => LedgerEntryStatus::class,
            'amount_sen' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function subOrder(): BelongsTo
    {
        return $this->belongsTo(SubOrder::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }
}
