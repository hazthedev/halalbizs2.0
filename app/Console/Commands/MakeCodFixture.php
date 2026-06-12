<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deterministic store + product for the Playwright COD journey (local only).
 * Prints the product slug.
 */
class MakeCodFixture extends Command
{
    protected $signature = 'e2e:cod-fixture';

    protected $description = 'Ensure the COD journey store/product exist and print the product slug';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Local environment only.');

            return self::FAILURE;
        }

        $seller = User::firstOrCreate(
            ['email' => 'cod-seller@halalbizs.test'],
            ['name' => 'COD Journey Seller', 'password' => 'password'],
        );
        $seller->forceFill(['email_verified_at' => now()])->save();
        $seller->assignRole('seller');

        $store = Store::withTrashed()->firstOrCreate(
            ['user_id' => $seller->id],
            [
                'name' => 'COD Journey Store',
                'description' => 'Fixture store for browser journeys.',
                'status' => StoreStatus::Approved,
                'state' => 'Selangor',
                'approved_at' => now(),
                'shipping_flat_fee_sen' => 500,
                'bank_details' => ['bank_name' => 'Maybank', 'account_name' => 'COD Journey Store', 'account_number' => '1234567890'],
            ],
        );
        $store->restore();
        $store->update(['status' => StoreStatus::Approved, 'holiday_mode' => false]);

        $product = Product::withTrashed()->where('store_id', $store->id)->first();

        if ($product === null) {
            $product = Product::factory()->for($store)->create([
                'category_id' => Category::whereDoesntHave('children')->first()->id,
                'name' => ['en' => 'COD Journey Widget', 'ms' => 'Widget Perjalanan COD'],
                'status' => ProductStatus::Live,
                'cod_enabled' => true,
            ]);
        }

        $product->restore();
        $product->update(['status' => ProductStatus::Live, 'cod_enabled' => true]);
        $product->variants()->update(['price_sen' => 1990, 'sale_price_sen' => null, 'stock' => 100]);

        $this->line($product->slug);

        return self::SUCCESS;
    }
}
