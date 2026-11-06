<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inci_name' => 'INCI '.fake()->unique()->lexify('????????'),
            'display_name' => 'Hoạt chất '.fake()->words(2, true),
            'function' => 'Dưỡng ẩm, phục hồi da',
        ];
    }
}
