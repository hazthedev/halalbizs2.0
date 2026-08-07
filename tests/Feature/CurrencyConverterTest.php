<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Production caches through a store that SERIALIZES. The default array
        // store keeps values in memory as-is, so without this the cache round
        // trip never happens and the regression below cannot fail.
        config(['cache.stores.array.serialize' => true]);
        Cache::purge('array'); // the manager memoizes stores; force a re-resolve

        Currency::create([
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
        ]);

        ExchangeRate::create([
            'currency_code' => 'USD',
            'rate' => '0.21000000',
            'margin_percent' => '0.00',
            'fetched_at' => now(),
        ]);
    }

    /**
     * The second call reads the cache back. cache.serializable_classes is false,
     * so anything object-shaped returns as __PHP_Incomplete_Class and the
     * property read fatals — which is what took down every page showing a price
     * once a shopper picked a non-MYR display currency.
     */
    public function test_display_survives_the_cache_round_trip(): void
    {
        $converter = app(CurrencyConverter::class);

        $miss = $converter->display(10000, 'USD'); // populates the cache
        $hit = $converter->display(10000, 'USD');  // reads it back

        $this->assertSame('≈ $ 21.00', $miss);
        $this->assertSame($miss, $hit);
    }

    public function test_myr_needs_no_conversion(): void
    {
        $this->assertSame('RM 100.00', app(CurrencyConverter::class)->display(10000, 'MYR'));
    }

    public function test_unknown_currency_falls_back_to_myr_formatting(): void
    {
        $this->assertSame('RM 100.00', app(CurrencyConverter::class)->display(10000, 'ZZZ'));
    }
}
