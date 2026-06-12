<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'decimal_places' => 2, 'is_base' => true, 'position' => 0, 'rate' => null],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => false, 'position' => 1, 'rate' => '0.21000000'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'decimal_places' => 2, 'is_base' => false, 'position' => 2, 'rate' => '0.28500000'],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'decimal_places' => 0, 'is_base' => false, 'position' => 3, 'rate' => '3450.00000000'],
        ];

        foreach ($currencies as $data) {
            $rate = $data['rate'];
            unset($data['rate']);

            Currency::updateOrCreate(['code' => $data['code']], $data + ['is_active' => true]);

            if ($rate !== null && ! ExchangeRate::where('currency_code', $data['code'])->exists()) {
                ExchangeRate::create([
                    'currency_code' => $data['code'],
                    'rate' => $rate,
                    'source' => 'manual',
                    'fetched_at' => now(),
                ]);
            }
        }
    }
}
