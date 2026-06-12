<?php

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\Translatable\HasTranslations;

class Product extends Model implements HasMedia
{
    use HasFactory, HasSlug, HasTranslations, InteractsWithMedia, LogsActivity, Searchable, SoftDeletes;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'store_id', 'category_id', 'brand_id', 'name', 'slug', 'description',
        'condition', 'status', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'cod_enabled', 'halal_status', 'halal_cert_number', 'halal_cert_expiry', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'status' => ProductStatus::class,
            'cod_enabled' => 'boolean',
            'halal_cert_expiry' => 'date',
            'published_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $product) => $product->getTranslation('name', 'en'))
            ->saveSlugsTo('slug');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');

        // One optional product video (mp4/webm, ≤30MB — enforced by the
        // seller form's mimetypes/max validation before the file gets here).
        $this->addMediaCollection('videos')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(400)->performOnCollections('images');
        $this->addMediaConversion('card')->width(800)->performOnCollections('images');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'category_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->where('is_default', true);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(ProductBoost::class);
    }

    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', ProductStatus::Live);
    }

    public function isLive(): bool
    {
        return $this->status === ProductStatus::Live;
    }

    /** Lowest effective price across variants, in sen. */
    public function minPriceSen(): int
    {
        return (int) $this->variants->map->effectivePriceSen()->min();
    }

    public function maxPriceSen(): int
    {
        return (int) $this->variants->map->effectivePriceSen()->max();
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ProductStatus::Live;
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['category', 'store', 'variants']);

        return [
            'id' => $this->id,
            'name_en' => $this->getTranslation('name', 'en'),
            'name_ms' => $this->getTranslation('name', 'ms', false),
            'description_en' => strip_tags($this->getTranslation('description', 'en')),
            'category' => $this->category?->getTranslation('name', 'en'),
            'store' => $this->store?->name,
            'min_price_sen' => $this->variants->isNotEmpty() ? $this->minPriceSen() : 0,
            'sold_count' => $this->sold_count,
        ];
    }
}
