<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One curated trust/detail signal on a product (M2.7). Definition (label, group,
 * type, searchable) lives in config('metafields.definitions').
 */
class ProductMetafield extends Model
{
    protected $fillable = ['product_id', 'key', 'value'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return array<string, mixed>|null */
    public function definition(): ?array
    {
        return config("metafields.definitions.{$this->key}");
    }

    public function label(): string
    {
        return __($this->definition()['label'] ?? str($this->key)->headline()->value());
    }

    public function group(): string
    {
        return $this->definition()['group'] ?? 'details';
    }

    public function type(): string
    {
        return $this->definition()['type'] ?? 'text';
    }
}
