<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Payout extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'payout_no', 'store_id', 'amount_sen', 'status', 'bank_snapshot',
        'requested_at', 'approved_at', 'paid_at', 'reference', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_sen' => 'integer',
            'bank_snapshot' => 'array',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public static function generatePayoutNo(): string
    {
        do {
            $no = 'PO'.now()->format('ym').strtoupper(Str::random(6));
        } while (static::where('payout_no', $no)->exists());

        return $no;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount_sen', 'reference'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class);
    }
}
