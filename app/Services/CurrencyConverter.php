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
    private const BASE = 'MYR';

    /** Minor units per major unit of the base currency: sen are 1/100 of a ringgit. */
    private const BASE_DECIMALS = 2;

    /**
     * Convert sen (MYR minor units) to the target currency's minor units.
     * Returns null when no rate is available.
     */
    public function convert(int $sen, string $toCurrency): ?int
    {
        if ($toCurrency === self::BASE) {
            return $sen;
        }

        $rate = $this->effectiveRate($toCurrency);

        if ($rate === null) {
            return null;
        }

        // Stored rates are MAJOR unit → MAJOR unit (the admin FX screen's own
        // hint is "e.g. 0.21" for USD). Multiplying sen by that yields a figure
        // still scaled to the BASE's two decimals, so it has to be shifted into
        // the target's own scale. Skip this and any currency whose decimals
        // differ from MYR's is wrong by 10^(difference): IDR (0 dp) rendered
        // RM 100.00 as "Rp 34,500,000" instead of "Rp 345,000".
        $decimals = $this->meta($toCurrency)['decimal_places'] ?? self::BASE_DECIMALS;

        return BigDecimal::of($sen)
            ->multipliedBy($rate)
            ->withPointMovedRight($decimals - self::BASE_DECIMALS)
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
        $displayCurrency ??= session('display_currency', self::BASE);

        if ($displayCurrency === self::BASE) {
            return Money::format($sen);
        }

        $currency = $this->meta($displayCurrency);

        $converted = $currency !== null ? $this->convert($sen, $displayCurrency) : null;

        if ($converted === null) {
            return Money::format($sen);
        }

        return '≈ '.Money::format($converted, $currency['symbol'], $currency['decimal_places']);
    }

    /**
     * Symbol + decimals for a currency code, or null when there is no such row.
     *
     * Caches the two scalars, never the model — cache.serializable_classes is
     * false (gadget-chain hardening), so a cached object comes back as
     * __PHP_Incomplete_Class and 500s on the first property read. Key renamed
     * from "currency:" so already-poisoned entries can't be read back.
     *
     * @return array{symbol: string, decimal_places: int}|null
     */
    private function meta(string $code): ?array
    {
        return Cache::remember(
            "currency-fmt:{$code}",
            now()->addHour(),
            fn () => Currency::where('code', $code)
                ->first(['symbol', 'decimal_places'])
                ?->only(['symbol', 'decimal_places'])
        );
    }
}
