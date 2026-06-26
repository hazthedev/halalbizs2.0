<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A public buyer question on a product, optionally answered by the seller. */
class ProductQuestion extends Model
{
    protected $fillable = [
        'product_id', 'store_id', 'user_id', 'question',
        'answer', 'answered_by', 'answered_at', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** The buyer who asked. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The seller staff/user who answered. */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->where('is_hidden', false);
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /** Privacy: first name + surname initial, e.g. "Aisyah Y.". */
    public function askerName(): string
    {
        $name = trim((string) $this->user?->name);

        if ($name === '') {
            return __('A shopper');
        }

        $parts = preg_split('/\s+/', $name);

        return count($parts) > 1
            ? $parts[0].' '.Str::upper(Str::substr(end($parts), 0, 1)).'.'
            : $parts[0];
    }
}
