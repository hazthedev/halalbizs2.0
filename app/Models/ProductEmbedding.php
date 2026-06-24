<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product's search vectors (M2.3). text_vector + image_vector are normalised
 * float arrays; similarity is their dot product (cosine, since normalised).
 */
class ProductEmbedding extends Model
{
    protected $fillable = ['product_id', 'text_vector', 'image_vector', 'model', 'dimensions'];

    protected function casts(): array
    {
        return [
            'text_vector' => 'array',
            'image_vector' => 'array',
            'dimensions' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
