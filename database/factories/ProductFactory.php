<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vendor_id' => Vendor::factory()->approved(),
            'category_id' => fn () => Category::query()->firstOrCreate(
                ['slug' => 'factory-products'],
                ['name' => 'Danh mục kiểm thử'],
            )->id,
            'brand_id' => null,
            'name' => fake()->words(4, true),
            'slug' => 'fixture-'.fake()->unique()->uuid(),
            'description' => null,
            'status' => 'draft',
            'reject_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'reject_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);
    }

    public function approved(?Vendor $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reject_reason' => null,
            'moderated_by' => $approver?->owner_user_id,
            'moderated_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'reject_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);
    }

    public function rejected(?Vendor $approver = null, ?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reject_reason' => $reason ?? fake()->sentence(),
            'moderated_by' => $approver?->owner_user_id,
            'moderated_at' => now(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'hidden',
            'reject_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
        ]);
    }
}
