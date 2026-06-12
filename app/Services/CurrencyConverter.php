<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;

/**
 * Display-only conversion. Storage, checkout, and settlement stay MYR
 * (locked decision). Integer-safe via brick/math — no float money.
 */
class CurrencyConverter
{
    /**
     * Convert sen (MYR minor units) to the target currency's minor units.
     * Returns null when no rate is available.
     */
    public function convert(int $sen, string $toCurrency): ?int
    {
        if ($toCurrency === 'MYR') {
            return $sen;
        }

        $rate = $this->effectiveRate($toCurrency);

        if ($rate === null) {
            return null;
        }

        return BigDecimal::of($sen)
            ->multipliedBy($rate)
            ->toScale(0, RoundingMode::HALF_UP)
            ->toInt();
    }

    /** Rate including margin, cached briefly. */
    public function effectiveRate(string $currencyCode): ?BigDecimal
    {
        $cached = Cache::remember(
            "fx:{$currencyCode}",
            now()->addMinutes(10),
            function () use ($currencyCode) {
                $row = ExchangeRate::latestFor($currencyCode);

                if ($row === null) {
                    return false;
                }

                return BigDecimal::of((string) $row->rate)
                    ->multipliedBy(
                        BigDecimal::one()->plus(BigDecimal::of((string) $row->margin_percent)->dividedBy(100, 8, RoundingMode::HALF_UP))
                    )
                    ->__toString();
            }
        );

        return $cached === false ? null : BigDecimal::of($cached);
    }

    /**
     * Format sen into the display currency: "≈ $ 12.30" or "RM 50.00".
     */
    public function display(int $sen, ?string $displayCurrency = null): string
    {
        $displayCurrency ??= session('display_currency', 'MYR');

        if ($displayCurrency === 'MYR') {
            return Money::format($sen);
        }

        $currency = Cache::remember(
            "currency:{$displayCurrency}",
            now()->addHour(),
            fn () => Currency::where('code', $displayCurrency)->first()
        );

        $converted = $currency !== null ? $this->convert($sen, $displayCurrency) : null;

        if ($converted === null) {
            return Money::format($sen);
        }

        return '≈ '.Money::format($converted, $currency->symbol, $currency->decimal_places);
    }
}
