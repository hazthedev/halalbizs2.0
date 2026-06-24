<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * The concierge's answer: a short bilingual text reply plus the LIVE products
 * it recommends, in display order (Hard Rule 5 — live products only).
 */
final class ConciergeReply
{
    /**
     * @param  Collection<int, Product>  $products
     */
    public function __construct(
        public readonly string $text,
        public readonly Collection $products,
    ) {}
}
