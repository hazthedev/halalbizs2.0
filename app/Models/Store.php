<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Store extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'status', 'rejection_reason',
        'holiday_mode', 'commission_rate', 'state', 'sst_registered', 'sst_number',
        'bank_details', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'holiday_mode' => 'boolean',
            'sst_registered' => 'boolean',
            'bank_details' => 'array',
            'approved_at' => 'datetime',
            'commission_rate' => 'decimal:2',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->singleFile();
        $this->addMediaCollection('banner')->singleFile();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'commission_rate', 'holiday_mode'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StoreLedgerEntry::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StoreDocument::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('status', StoreStatus::Approved);
    }

    public function isApproved(): bool
    {
        return $this->status === StoreStatus::Approved;
    }

    public function availableBalanceSen(): int
    {
        return (int) $this->ledgerEntries()
            ->where('status', LedgerEntryStatus::Available)
            ->whereNull('payout_id')
            ->sum('amount_sen');
    }
}
