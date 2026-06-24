<?php

namespace App\Models;

use App\Enums\LedgerEntryStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\SubOrderStatus;
use App\Support\ReservedSubdomains;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'holiday_mode', 'commission_rate', 'state', 'sst_registered', 'sst_number', 'tin',
        'bank_details', 'approved_at',
        'shipping_mode', 'shipping_flat_fee_sen', 'shipping_matrix', 'free_shipping_over_sen',
        'shipping_origin_postcode',
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
            'service_rating_avg' => 'decimal:2',
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

    /**
     * Canonical storefront URL for links. Uses the per-store subdomain only
     * when explicitly enabled (production with wildcard DNS); otherwise the
     * always-resolvable /s/{slug} path so links work in any environment.
     */
    public function storefrontUrl(): string
    {
        return config('app.store_subdomains_enabled')
            ? $this->subdomainUrl()
            : route('store.show', $this->slug);
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

    public function boosts(): HasMany
    {
        return $this->hasMany(ProductBoost::class);
    }

    public function health(): HasOne
    {
        return $this->hasOne(SellerHealth::class);
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

    /**
     * Held in escrow (M1.4): expected net for paid orders still in flight
     * (confirmed → delivered, not yet completed). Released to the available
     * balance via the ledger when the order completes / the guarantee window
     * lapses. Estimated commission mirrors LedgerService's integer math.
     */
    public function heldBalanceSen(): int
    {
        return (int) $this->subOrders()
            ->whereIn('status', [
                SubOrderStatus::Confirmed,
                SubOrderStatus::Processing,
                SubOrderStatus::Shipped,
                SubOrderStatus::Delivered,
            ])
            ->whereHas('order', fn ($order) => $order->where('payment_status', PaymentStatus::Paid))
            ->get(['items_subtotal_sen', 'total_sen', 'commission_rate'])
            ->sum(function ($subOrder) {
                $rateBp = (int) round((float) $subOrder->commission_rate * 100);
                $commission = intdiv($subOrder->items_subtotal_sen * $rateBp + 5000, 10000);

                return max(0, $subOrder->total_sen - $commission);
            });
    }
}
