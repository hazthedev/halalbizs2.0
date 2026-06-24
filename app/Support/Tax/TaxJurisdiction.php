<?php

namespace App\Support\Tax;

use App\Enums\TaxClass;

/**
 * A destination tax regime: maps each TaxClass to a rate in integer basis
 * points (525 = 5.25%). Rates are statutory; all arithmetic stays integer.
 * Malaysia (SST) is the first concrete regime — see TaxService::registry().
 * An unknown destination resolves to an empty (zero-rated) regime so worldwide
 * orders are never wrongly taxed until a ruleset is configured.
 */
final class TaxJurisdiction
{
    /** @param  array<string, int>  $rates  TaxClass->value => basis points */
    public function __construct(
        public readonly string $country,
        public readonly string $taxName,
        public readonly array $rates,
    ) {}

    public function rateBpFor(TaxClass $class): int
    {
        return $this->rates[$class->value] ?? 0;
    }

    /** True when this regime taxes anything (used to skip work / display). */
    public function appliesTax(): bool
    {
        return array_sum($this->rates) > 0;
    }
}
