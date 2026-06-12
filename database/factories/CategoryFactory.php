<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return [
            'name' => ['en' => $name, 'ms' => $name],
            'is_active' => true,
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
