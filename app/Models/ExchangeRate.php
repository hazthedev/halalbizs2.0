<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only; the latest row per currency wins.
 */
class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = ['currency_code', 'rate', 'margin_percent', 'source', 'fetched_at'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'margin_percent' => 'decimal:2',
            'fetched_at' => 'datetime',
        ];
    }

    public static function latestFor(string $code): ?self
    {
        return static::where('currency_code', $code)
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->first();
    }
}
