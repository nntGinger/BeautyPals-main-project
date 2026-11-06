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
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'receiver' => fake()->name(),
            'phone' => '09'.fake()->numerify('########'),
            'line' => fake()->streetAddress(),
            'ward' => 'Phường Bến Nghé',
            'district' => 'Quận 1',
            'city' => 'TP. Hồ Chí Minh',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
