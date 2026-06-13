<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deterministic store + product for the Playwright COD/regression journeys
 * (local only). Resets the demo buyer's cart and prior fixture orders so each
 * journey starts from a clean slate, then prints the product slug.
 */
class MakeCodFixture extends Command
{
    protected $signature = 'e2e:cod-fixture {--with-video}';

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

        // Clear stale orders on this store so the seller's New tab + buyer
        // order tabs only ever show the order the current journey places.
        Order::whereHas('subOrders', fn ($q) => $q->where('store_id', $store->id))->get()
            ->each(fn (Order $order) => $order->delete());

        // Empty the demo buyer's cart so checkout contains only the fixture item.
        $buyer = User::where('email', 'buyer@halalbizs.test')->first();
        $buyer?->cart?->items()->delete();

        if ($this->option('with-video') && ! $product->hasMedia('videos')) {
            $path = tempnam(sys_get_temp_dir(), 'e2e').'.mp4';
            file_put_contents($path, $this->minimalMp4());
            $product->addMedia($path)->usingFileName('demo.mp4')->toMediaCollection('videos');
        }

        $this->line($product->slug);

        return self::SUCCESS;
    }

    /** A minimal MP4 ftyp box — enough for finfo to detect video/mp4 and for a <video> element to mount. */
    private function minimalMp4(): string
    {
        return "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isommp42"
            ."\x00\x00\x00\x08free";
    }
}
