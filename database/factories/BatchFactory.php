<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Sku;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(20, 200);

        return [
            'sku_id' => Sku::factory(),
            'batch_no' => 'LOT-'.strtoupper(fake()->unique()->bothify('????-####')),
            'mfg_date' => now()->subMonths(3)->toDateString(),
            'exp_date' => now()->addMonths(12)->toDateString(),
            'qty_received' => $qty,
            'qty_on_hand' => $qty,
            'qty_reserved' => 0,
            'status' => 'active',
        ];
    }

    public function expiringSoon(int $days = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'exp_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'mfg_date' => now()->subMonths(24)->toDateString(),
            'exp_date' => now()->subDays(5)->toDateString(),
            'status' => 'expired',
        ]);
    }
}
