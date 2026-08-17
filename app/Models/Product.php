<?php

namespace App\Models;

use App\Enums\HalalStatus;
use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\TaxClass;
use App\Support\JsonSearch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'condition', 'status', 'tax_class', 'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'cod_enabled', 'halal_status', 'halal_cert_number', 'halal_cert_expiry', 'published_at',
        'halal_certificate_id', 'halal_batch_code', 'halal_packed_on',
    ];

    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'status' => ProductStatus::class,
            'tax_class' => TaxClass::class,
            'cod_enabled' => 'boolean',
            'halal_status' => HalalStatus::class,
            'halal_cert_expiry' => 'date',
            'halal_packed_on' => 'date',
            // Set by certificates:watch-expiry when it takes a product down, so
            // renewal restores exactly those and not seller-delisted stock.
            'halal_delisted_at' => 'datetime',
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

    /** The certificate this SKU is bound to. Nullable: a product may exist
     *  before its certificate record is attached. */
    public function halalCertificate(): BelongsTo
    {
        return $this->belongsTo(HalalCertificate::class, 'halal_certificate_id');
    }

    /**
     * The ONE owner of the halal verdict. Every surface that renders a badge
     * must ask this, never re-derive.
     *
     * It did not used to be. The PDP badge derived its own answer from
     * `valid_to` alone while the register called `HalalCertificate::isValid()`,
     * which checks BOTH bounds — so a certificate whose term had not started
     * yet read "verified" on the product page and "NOT VALID" in the register.
     * On the 2026-08-07 preview that was 17 of 24 certificates, badging 134 of
     * the 166 products showing a green tick.
     *
     * It also fails CLOSED now. The old badge keyed off `halal_cert_number` —
     * a free-text string on the product row — so anything typed there rendered
     * as verified, and a null expiry made `lapsed` false, i.e. green with no
     * date. A claim of "verified" requires a certificate RECORD that says so.
     *
     * A certificate awaiting review reads as 'pending', not 'verified'. Binding
     * only offers approved certificates, so this should be unreachable — which
     * is exactly why it is here: the badge is the trust claim, and it must fail
     * closed if any other writer ever binds an unapproved record (H-6).
     *
     * @return 'verified'|'lapsed'|'pending'|'unverified'
     */
    public function halalVerdict(): string
    {
        $cert = $this->halalCertificate;

        if ($cert === null) {
            return 'unverified';
        }

        if (! $cert->isApproved()) {
            return 'pending';
        }

        if ($cert->isValid()) {
            return 'verified';
        }

        // Not valid, so it is one of the two edges: lapsed, or not in force yet.
        return $cert->valid_to->lt(now()->startOfDay()) ? 'lapsed' : 'pending';
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

    /** Specific attribute values assigned to this product (faceted search, M1.3). */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    /** Curated trust/detail metafields (M2.7). */
    public function metafields(): HasMany
    {
        return $this->hasMany(ProductMetafield::class);
    }

    public function metafield(string $key): ?string
    {
        return $this->metafields->firstWhere('key', $key)?->value;
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

    public function questions(): HasMany
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function boosts(): HasMany
    {
        return $this->hasMany(ProductBoost::class);
    }

    /**
     * Buyer-visible catalogue: a live SKU on an approved store. The store
     * clause is here, not on each screen, so suspending a store takes its
     * goods off every read path at once (PDP, listing, search, cart, checkout)
     * — a suspension reason can be "selling non-halal goods as halal".
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('status', ProductStatus::Live)
            ->whereHas('store', fn (Builder $store) => $store->approved());
    }

    /**
     * Real-column keyword search — the MySQL/SQLite fallback for when Scout's
     * Meilisearch index isn't available. Scout's collection/database engines
     * query toSearchableArray() keys as if they were columns (name_en, category,
     * min_price_sen…) and 500 on any SQL connection. `name`/`description` are
     * translatable JSON columns, so a LIKE on the raw JSON text matches any locale.
     */
    #[Scope]
    protected function keywordSearch(Builder $query, ?string $term): void
    {
        // ⚠ LOWER() on both sides, not a plain LIKE.
        // `name` and `description` are JSON columns, and MySQL gives JSON a
        // BINARY collation — so `LIKE '%beras%'` never matches "Beras Wangi…".
        // SQLite stores them as TEXT with case-insensitive LIKE, so this was
        // invisible locally and broke every product-name search on the preview:
        // "Beras" returned 8 results, "beras" returned 0. Store and brand names
        // are ordinary VARCHAR and did match, which is what made it look like
        // search "half worked".
        // The escaping and lowercasing now live in JsonSearch (M-19), because
        // three other readers needed the same recipe and hand-rolling it a
        // fourth time is how the next copy drifts.
        $like = JsonSearch::pattern($term);

        $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw(JsonSearch::clause('name'), [$like])
                ->orWhereRaw(JsonSearch::clause('description'), [$like])
                ->orWhereHas('store', fn (Builder $s) => $s->whereRaw(JsonSearch::clause('name'), [$like]))
                ->orWhereHas('brand', fn (Builder $b) => $b->whereRaw(JsonSearch::clause('name'), [$like]));
        });
    }

    /**
     * Relevance-ordered product IDs for a keyword term: Meilisearch via Scout
     * when configured, else the real-column SQL search above (the only path that
     * works on a host without Meilisearch). Empty term → no results.
     *
     * @return array<int>
     */
    public static function searchKeywordIds(?string $term): array
    {
        $term = trim((string) $term);

        if ($term === '') {
            return [];
        }

        if (config('scout.driver') === 'meilisearch') {
            return static::search($term)->keys()->all();
        }

        // "Relevance" has to mean relevance. This ordered by `sold_count` alone,
        // so a product that merely MENTIONS the term in its description outranked
        // one whose NAME is the term if it sold better: searching "honey" put
        // Hazelnut Spread with Cocoa first and Manuka Honey UMF10 fifth, which
        // reads as broken search and costs trust in the whole catalogue.
        //
        // Two tiers, then popularity inside each: name matches first, everything
        // else (description / store / brand matches) after. Same lowered,
        // escaped LIKE the scope uses — see keywordSearch() for why LOWER() on
        // both sides is load-bearing on MySQL's binary-collated JSON.
        $needle = mb_strtolower($term);
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $needle).'%';

        return static::query()
            ->keywordSearch($term)
            ->orderByRaw("CASE WHEN LOWER(name) LIKE ? ESCAPE '!' THEN 0 ELSE 1 END", [$like])
            ->orderByDesc('sold_count')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();
    }

    /** PHP-side twin of the `live` scope above — same rule, same store clause. */
    public function isLive(): bool
    {
        return $this->status === ProductStatus::Live && (bool) $this->store?->isApproved();
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
        return $this->isLive();
    }

    public function toSearchableArray(): array
    {
        $this->loadMissing(['category', 'store', 'variants', 'attributeValues', 'metafields']);

        return [
            'id' => $this->id,
            'name_en' => $this->getTranslation('name', 'en'),
            'name_ms' => $this->getTranslation('name', 'ms', false),
            'name_vi' => $this->getTranslation('name', 'vi', false),
            'description_en' => strip_tags($this->getTranslation('description', 'en')),
            'description_vi' => strip_tags($this->getTranslation('description', 'vi', false)),
            'category' => $this->category?->getTranslation('name', 'en'),
            'store' => $this->store?->name,
            'min_price_sen' => $this->variants->isNotEmpty() ? $this->minPriceSen() : 0,
            'sold_count' => $this->sold_count,
            // Faceting (Meilisearch filterableAttributes, M1.3).
            'attribute_value_ids' => $this->attributeValues->pluck('id')->all(),
            // Trust/detail signals — ingredients, halal body, SIRIM, origin (M2.7).
            'metafields' => $this->searchableMetafieldText(),
        ];
    }

    /** The text blended into this product's search embedding (M2.3). */
    public function embeddingText(): string
    {
        $this->loadMissing(['category', 'metafields']);

        return collect([
            $this->getTranslation('name', 'en'),
            $this->getTranslation('name', 'ms', false),
            $this->getTranslation('name', 'vi', false),
            strip_tags($this->getTranslation('description', 'en')),
            strip_tags($this->getTranslation('description', 'vi', false)),
            $this->category?->getTranslation('name', 'en'),
            $this->searchableMetafieldText(),
        ])->filter()->implode(' ');
    }

    /** Concatenated text of the searchable metafields (M2.7). */
    private function searchableMetafieldText(): string
    {
        $searchableKeys = array_keys(array_filter(
            (array) config('metafields.definitions', []),
            fn ($def) => $def['searchable'] ?? false,
        ));

        return $this->metafields
            ->whereIn('key', $searchableKeys)
            ->pluck('value')
            ->implode(' ');
    }
}
