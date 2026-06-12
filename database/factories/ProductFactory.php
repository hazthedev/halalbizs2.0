<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'store_id' => Store::factory()->approved(),
            'category_id' => Category::factory(),
            'name' => ['en' => $name, 'ms' => $name],
            'description' => [
                'en' => '<p>'.implode('</p><p>', fake()->paragraphs(3)).'</p>',
                'ms' => '<p>'.implode('</p><p>', fake()->paragraphs(2)).'</p>',
            ],
            'condition' => 'new',
            'status' => ProductStatus::Live,
            'weight_grams' => fake()->numberBetween(50, 5000),
            'cod_enabled' => fake()->boolean(80),
            'sold_count' => fake()->numberBetween(0, 2500),
            'published_at' => now()->subDays(fake()->numberBetween(0, 60)),
        ];
    }

    /**
     * Single default variant (the no-variation product shape).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->variants()->count() === 0) {
                ProductVariant::factory()->default()->create(['product_id' => $product->id]);
            }
        });
    }

    /**
     * Build the full option matrix: e.g. ->withVariants(colour: 3, size: 2) → 6 variants.
     */
    public function withVariants(int $colour = 3, int $size = 2): static
    {
        return $this->afterCreating(function (Product $product) use ($colour, $size) {
            // configure() already added a default variant — the matrix replaces it.
            $product->variants()->delete();

            $colours = collect(['Red', 'Blue', 'Green', 'Black', 'White'])->take($colour);
            $sizes = collect(['S', 'M', 'L', 'XL'])->take($size);

            $colourOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Colour', 'position' => 0]);
            $sizeOption = ProductOption::create(['product_id' => $product->id, 'name' => 'Size', 'position' => 1]);

            $colourValues = $colours->values()->map(fn ($value, $i) => $colourOption->values()->create(['value' => $value, 'position' => $i]));
            $sizeValues = $sizes->values()->map(fn ($value, $i) => $sizeOption->values()->create(['value' => $value, 'position' => $i]));

            $basePriceSen = fake()->numberBetween(500, 50000);
            $position = 0;

            foreach ($colourValues as $colourValue) {
                foreach ($sizeValues as $sizeValue) {
                    ProductVariant::factory()->create([
                        'product_id' => $product->id,
                        'options_label' => "{$colourValue->value} / {$sizeValue->value}",
                        'option_value_ids' => [$colourValue->id, $sizeValue->id],
                        'price_sen' => $basePriceSen + fake()->numberBetween(0, 2000),
                        'is_default' => $position === 0,
                        'position' => $position++,
                    ]);
                }
            }
        });
    }
}
