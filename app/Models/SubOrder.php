<?php

namespace App\Models;

use App\Enums\SubOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Status NEVER changes by assignment — only via SubOrderStatusService::transition()
 * (CLAUDE.md hard rule 2).
 */
class SubOrder extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'sub_order_no', 'order_id', 'store_id', 'status',
        'items_subtotal_sen', 'shipping_fee_sen', 'shop_discount_sen', 'total_sen',
        'commission_rate', 'commission_sen', 'tracking_courier', 'tracking_no',
        'shipped_at', 'delivered_at', 'completed_at', 'auto_complete_at',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubOrderStatus::class,
            'items_subtotal_sen' => 'integer',
            'shipping_fee_sen' => 'integer',
            'shop_discount_sen' => 'integer',
            'total_sen' => 'integer',
            'commission_rate' => 'decimal:2',
            'commission_sen' => 'integer',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'auto_complete_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function generateSubOrderNo(): string
    {
        do {
            $no = 'SO'.now()->format('ym').strtoupper(Str::random(6));
        } while (static::where('sub_order_no', $no)->exists());

        return $no;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class);
    }

    public function returnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class);
    }
}
