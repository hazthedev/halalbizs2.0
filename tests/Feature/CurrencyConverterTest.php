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

    /**
     * Stored rates are major→major (the admin FX screen's hint is "e.g. 0.21").
     * A currency with fewer decimals than MYR must be shifted down, or the
     * figure is out by 10^(difference). IDR is live on the preview dropdown and
     * rendered RM 100.00 as "Rp 34,500,000" — 100× the real ~Rp 345,000.
     */
    public function test_a_zero_decimal_currency_is_not_inflated_by_the_base_scale(): void
    {
        Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'decimal_places' => 0,
        ]);
        ExchangeRate::create([
            'currency_code' => 'IDR',
            'rate' => '3450.00000000',
            'margin_percent' => '0.00',
            'fetched_at' => now(),
        ]);

        $this->assertSame('≈ Rp 345,000', app(CurrencyConverter::class)->display(10000, 'IDR'));
    }

    /**
     * The same trap, on the row we actually ship. VND is zero-decimal like IDR,
     * so this asserts the SEEDED value rather than a hand-built row — set
     * decimal_places to 2 in CurrencySeeder and RM 100 renders as 590,000,000.
     */
    public function test_the_seeded_vnd_row_renders_at_zero_decimals(): void
    {
        $this->seed(\Database\Seeders\CurrencySeeder::class);

        $this->assertSame('≈ VND 590,000', app(CurrencyConverter::class)->display(10000, 'VND'));
    }

    /** The other direction: more decimals than the base must shift UP. */
    public function test_a_three_decimal_currency_shifts_the_other_way(): void
    {
        Currency::create([
            'code' => 'KWD',
            'name' => 'Kuwaiti Dinar',
            'symbol' => 'KD',
            'decimal_places' => 3,
        ]);
        ExchangeRate::create([
            'currency_code' => 'KWD',
            'rate' => '0.07000000',
            'margin_percent' => '0.00',
            'fetched_at' => now(),
        ]);

        // RM 100.00 × 0.07 = KD 7.000
        $this->assertSame('≈ KD 7.000', app(CurrencyConverter::class)->display(10000, 'KWD'));
    }

    /** Control: the two-decimal case that already worked must not move. */
    public function test_a_two_decimal_currency_is_unchanged(): void
    {
        $this->assertSame('≈ $ 21.00', app(CurrencyConverter::class)->display(10000, 'USD'));
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
