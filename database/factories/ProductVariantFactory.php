<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $priceSen = fake()->numberBetween(500, 50000);
        $onSale = fake()->boolean(30);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'price_sen' => $priceSen,
            'sale_price_sen' => $onSale ? intdiv($priceSen * fake()->numberBetween(50, 90), 100) : null,
            'sale_starts_at' => $onSale ? now()->subDay() : null,
            'sale_ends_at' => $onSale ? now()->addDays(fake()->numberBetween(1, 14)) : null,
            'stock' => fake()->numberBetween(0, 200),
            'is_default' => false,
            'position' => 0,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true, 'options_label' => null, 'option_value_ids' => null]);
    }
}
