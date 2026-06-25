<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubOrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\SubOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo reviews. The reviews feature is fully built; without data the product
 * pages, cards and seller panel show nothing. This populates it the way a live
 * marketplace would: each review hangs off a COMPLETED sub-order's order_item
 * (the same shape the real write flow produces), so ReviewObserver fills the
 * product + store rating aggregates exactly as production would.
 *
 * Also leaves a couple of Completed, un-reviewed orders for buyer@halalbizs.test
 * so the storefront write flow (Account → Orders → Completed → Rate) is testable.
 *
 * FAKER-FREE on purpose (no model factories): so it runs on a --no-dev install
 * (the cPanel deploy webhook), where dev deps aren't present. Idempotent — skips
 * if any review exists — so it's safe to leave in deploy.sh.
 */
class DemoReviewsSeeder extends Seeder
{
    /** Realistic marketplace skew — mostly happy, a few critical. */
    private const RATING_WEIGHTS = [5 => 50, 4 => 28, 3 => 12, 2 => 6, 1 => 4];

    private const COMMENTS = [
        'Exactly as described, arrived well-packed and on time. Very happy with it.',
        'Good quality for the price. Would buy from this seller again.',
        'Decent product but delivery took a little longer than expected.',
        'Works perfectly, my whole family loves it. Highly recommend.',
        'The material feels premium and the finish is lovely.',
        'Met my expectations. Nothing extraordinary but solid value.',
        'Seller was responsive and helpful when I had a question.',
        'Slightly smaller than I imagined but still great.',
        'Five stars — fast shipping and the item is exactly right.',
        'Average. It does the job but I expected a bit more.',
        'Beautiful packaging and the product is even better in person.',
        'Reliable and well made. No complaints at all.',
    ];

    private const REVIEWERS = [
        'Nurul Aina', 'Ahmad Faiz', 'Siti Mariam', 'Tan Wei Ming', 'Priya Kumar',
        'Lim Mei Ling', 'Hafiz Rahman', 'Chong Kar Wai', 'Aisyah Yusof', 'Daniel Tan',
        'Farah Iskandar', 'Vimal Raj',
    ];

    public function run(): void
    {
        if (Review::query()->exists()) {
            $this->command?->info('DemoReviewsSeeder skipped — reviews already present.');

            return;
        }

        $products = Product::query()->with('variants')->whereHas('variants')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('DemoReviewsSeeder: no products — run DemoSeeder first.');

            return;
        }

        $buyers = $this->reviewerPool();
        $reviewed = 0;

        // Populate ~45% of products with 1–4 reviews each.
        foreach ($products as $product) {
            if (random_int(1, 100) > 45) {
                continue;
            }

            foreach (range(1, random_int(1, 4)) as $n) {
                $buyer = $buyers->random();
                $item = $this->completedItem($buyer, $product);

                Review::create([
                    'order_item_id' => $item->id,
                    'product_id' => $product->id,
                    'store_id' => $product->store_id,
                    'user_id' => $buyer->id,
                    'rating' => $this->weightedRating(),
                    'comment' => random_int(1, 100) <= 80 ? self::COMMENTS[array_rand(self::COMMENTS)] : null,
                    // ~1 in 3 also leaves a seller service rating (one per sub-order).
                    'seller_rating' => random_int(1, 3) === 1 ? $this->weightedRating() : null,
                    'seller_comment' => null,
                ]);

                $reviewed++;
            }
        }

        // Two Completed, un-reviewed orders for the demo buyer → exercises the UI write flow.
        $demoBuyer = User::query()->where('email', 'buyer@halalbizs.test')->first();

        if ($demoBuyer !== null) {
            foreach ($products->random(min(2, $products->count())) as $product) {
                $this->completedItem($demoBuyer, $product); // item only, no review
            }
        }

        $this->command?->info("DemoReviewsSeeder: created {$reviewed} reviews across the catalog.");
    }

    /** A pool of plain reviewer users (no factory/faker). */
    private function reviewerPool()
    {
        return collect(self::REVIEWERS)->map(function (string $name, int $i): User {
            return User::query()->firstOrCreate(
                ['email' => 'demo-reviewer-'.($i + 1).'@reviews.test'],
                ['name' => $name, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
        });
    }

    /** Build a Completed sub-order with one snapshot item for $buyer/$product. */
    private function completedItem(User $buyer, Product $product): OrderItem
    {
        $variant = $product->variants->first();
        $price = $variant->effectivePriceSen();

        $order = Order::query()->forceCreate([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $buyer->id,
            'payment_method' => PaymentMethod::Cod,
            'payment_status' => PaymentStatus::Paid,
            'shipping_address' => [
                'recipient_name' => $buyer->name,
                'phone' => '+60123456789',
                'line1' => '1 Jalan Demo',
                'line2' => null,
                'postcode' => '47500',
                'city' => 'Subang Jaya',
                'state' => 'Selangor',
                'country' => 'MY',
            ],
            'subtotal_sen' => $price,
            'shipping_total_sen' => 500,
            'discount_total_sen' => 0,
            'grand_total_sen' => $price + 500,
            'display_currency' => 'MYR',
            'display_rate' => 1,
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        $subOrder = SubOrder::query()->forceCreate([
            'sub_order_no' => SubOrder::generateSubOrderNo(),
            'order_id' => $order->id,
            'store_id' => $product->store_id,
            'status' => SubOrderStatus::Completed,
            'items_subtotal_sen' => $price,
            'shipping_fee_sen' => 500,
            'shop_discount_sen' => 0,
            'total_sen' => $price + 500,
            'commission_rate' => '5.00',
            'completed_at' => now(),
        ]);

        return $subOrder->items()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->getTranslation('name', 'en'),
            'variant_label' => $variant->options_label,
            'unit_price_sen' => $price,
            'qty' => 1,
            'line_total_sen' => $price,
        ]);
    }

    private function weightedRating(): int
    {
        $roll = random_int(1, array_sum(self::RATING_WEIGHTS));

        foreach (self::RATING_WEIGHTS as $rating => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $rating;
            }
        }

        return 5;
    }
}
