<?php

namespace Database\Factories;

use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'description' => fake()->paragraph(),
            'status' => StoreStatus::Pending,
            'state' => fake()->randomElement(self::MY_STATES),
            'bank_details' => [
                'bank_name' => fake()->randomElement(['Maybank', 'CIMB', 'Public Bank', 'RHB', 'Bank Islam']),
                'account_name' => $name,
                'account_number' => fake()->numerify('##########'),
            ],
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => StoreStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public const MY_STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang',
        'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor',
        'Terengganu', 'Kuala Lumpur', 'Labuan', 'Putrajaya',
    ];
}
