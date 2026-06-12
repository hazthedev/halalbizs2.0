<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Enums\StoreStatus;
use App\Support\ReservedSubdomains;
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
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Store extends Model implements HasMedia
{
    use HasFactory, HasSlug, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'status', 'rejection_reason',
        'holiday_mode', 'commission_rate', 'state', 'sst_registered', 'sst_number',
        'bank_details', 'approved_at',
        'shipping_mode', 'shipping_flat_fee_sen', 'shipping_matrix', 'free_shipping_over_sen',
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
            'shipping_flat_fee_sen' => 'integer',
            'shipping_matrix' => 'array',
            'free_shipping_over_sen' => 'integer',
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

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(400)->performOnCollections('logo');
        $this->addMediaConversion('card')->width(1200)->performOnCollections('banner');
    }

    protected static function booted(): void
    {
        // A store slug becomes a public subdomain — never let it shadow a
        // reserved host (www, admin, …). Registered on creating/updating so
        // it runs AFTER Sluggable generates the slug on the same events.
        $guard = function (Store $store) {
            if ($store->slug !== null && ReservedSubdomains::isReserved($store->slug)) {
                $store->slug .= '-shop';
            }
        };

        static::creating($guard);
        static::updating($guard);
    }

    /** Public storefront URL on the store's own subdomain. */
    public function subdomainUrl(): string
    {
        $scheme = parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'http';

        return "{$scheme}://{$this->slug}.".config('app.store_subdomain_base');
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

    /**
     * Balance = SUM of available entries. Payout requests already write a
     * negative `payout` entry, so no extra filtering is needed.
     */
    public function availableBalanceSen(): int
    {
        return (int) $this->ledgerEntries()
            ->where('status', LedgerEntryStatus::Available)
            ->sum('amount_sen');
    }
}
