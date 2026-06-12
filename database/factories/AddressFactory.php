<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement(['Home', 'Office']),
            'recipient_name' => fake()->name(),
            'phone' => '01'.fake()->numerify('#-### ####'),
            'line1' => fake()->buildingNumber().', Jalan '.fake()->lastName(),
            'line2' => fake()->optional()->secondaryAddress(),
            'postcode' => fake()->numerify('#####'),
            'city' => fake()->city(),
            'state' => fake()->randomElement(StoreFactory::MY_STATES),
            'country' => 'MY',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
