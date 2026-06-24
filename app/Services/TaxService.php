<?php

namespace App\Services;

use App\Enums\TaxClass;
use App\Support\Tax\TaxJurisdiction;

/**
 * Worldwide, jurisdiction-aware tax engine (docs/ROADMAP.md M0.1). Tax is
 * computed per line in integer sen from: destination jurisdiction × seller
 * registration × product tax class. Malaysia SST is the first concrete ruleset.
 * Tax is charged ONLY when the seller is tax-registered — most micro-sellers
 * are not, so the common case is zero. Money never touches a float (Hard Rule 1).
 */
class TaxService
{
    /** Resolve the tax regime for a destination country (ISO-3166 alpha-2). */
    public function jurisdictionFor(?string $country): TaxJurisdiction
    {
        $code = strtoupper(trim((string) ($country ?? ''))) ?: 'MY';

        return $this->registry()[$code] ?? new TaxJurisdiction($code, 'Tax', []);
    }

    /** Rate (basis points) that applies to a line, gated by seller registration. */
    public function rateBpFor(TaxClass $class, bool $sellerRegistered, TaxJurisdiction $jurisdiction): int
    {
        return $sellerRegistered ? $jurisdiction->rateBpFor($class) : 0;
    }

    /**
     * Tax in sen for a taxable base. Rounds half-up with integer math only
     * (mirrors the commission rounding in LedgerService).
     */
    public function lineTaxSen(int $baseSen, TaxClass $class, bool $sellerRegistered, TaxJurisdiction $jurisdiction): int
    {
        $bp = $this->rateBpFor($class, $sellerRegistered, $jurisdiction);

        if ($bp <= 0 || $baseSen <= 0) {
            return 0;
        }

        return intdiv($baseSen * $bp + 5000, 10000);
    }

    /**
     * Registered destination tax regimes. Add a country here to switch tax on
     * for it — the rest of the engine (checkout, invoice, ledger) is generic.
     *
     * @return array<string, TaxJurisdiction>
     */
    private function registry(): array
    {
        return [
            // Malaysia — Sales & Service Tax (SST). Full enforcement from 2026.
            'MY' => new TaxJurisdiction('MY', 'SST', [
                TaxClass::Standard->value => 1000,       // Sales Tax 10%
                TaxClass::Reduced->value => 500,         // Sales Tax 5%
                TaxClass::Service->value => 800,         // Service Tax 8%
                TaxClass::ServiceReduced->value => 600,  // Service Tax 6%
                TaxClass::Exempt->value => 0,
                TaxClass::ZeroRated->value => 0,
            ]),
        ];
    }
}
