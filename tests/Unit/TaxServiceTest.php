<?php

use App\Enums\TaxClass;
use App\Services\TaxService;

beforeEach(function () {
    $this->tax = new TaxService;
    $this->my = $this->tax->jurisdictionFor('MY');
});

test('Malaysia SST rates resolve per tax class for a registered seller', function () {
    // RM100.00 base.
    expect($this->tax->lineTaxSen(10000, TaxClass::Standard, true, $this->my))->toBe(1000)        // 10%
        ->and($this->tax->lineTaxSen(10000, TaxClass::Reduced, true, $this->my))->toBe(500)        // 5%
        ->and($this->tax->lineTaxSen(10000, TaxClass::Service, true, $this->my))->toBe(800)        // 8%
        ->and($this->tax->lineTaxSen(10000, TaxClass::ServiceReduced, true, $this->my))->toBe(600) // 6%
        ->and($this->tax->lineTaxSen(10000, TaxClass::Exempt, true, $this->my))->toBe(0)
        ->and($this->tax->lineTaxSen(10000, TaxClass::ZeroRated, true, $this->my))->toBe(0);
});

test('tax is zero when the seller is not tax-registered', function () {
    expect($this->tax->lineTaxSen(10000, TaxClass::Standard, false, $this->my))->toBe(0)
        ->and($this->tax->rateBpFor(TaxClass::Standard, false, $this->my))->toBe(0);
});

test('rate basis points are exposed for snapshotting', function () {
    expect($this->tax->rateBpFor(TaxClass::Standard, true, $this->my))->toBe(1000)
        ->and($this->tax->rateBpFor(TaxClass::Reduced, true, $this->my))->toBe(500);
});

test('rounding is half-up and integer-only', function () {
    // 5% of 199 sen = 9.95 → 10
    expect($this->tax->lineTaxSen(199, TaxClass::Reduced, true, $this->my))->toBe(10)
        // 5% of 190 sen = 9.5 → 10 (half-up)
        ->and($this->tax->lineTaxSen(190, TaxClass::Reduced, true, $this->my))->toBe(10)
        // 5% of 180 sen = 9.0 → 9
        ->and($this->tax->lineTaxSen(180, TaxClass::Reduced, true, $this->my))->toBe(9);
});

test('a null or empty destination defaults to Malaysia', function () {
    expect($this->tax->jurisdictionFor(null)->country)->toBe('MY')
        ->and($this->tax->jurisdictionFor('')->country)->toBe('MY')
        ->and($this->tax->jurisdictionFor('my')->appliesTax())->toBeTrue();
});

test('unknown destinations are zero-rated until a ruleset is configured', function () {
    $sg = $this->tax->jurisdictionFor('SG');

    expect($sg->appliesTax())->toBeFalse()
        ->and($this->tax->lineTaxSen(10000, TaxClass::Standard, true, $sg))->toBe(0);
});
