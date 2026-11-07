<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_user_id' => User::factory()->vendor(),
            'store_name' => fake()->unique()->company(),
            'tax_code' => fake()->unique()->numerify('##########'),
            'phone' => fake()->numerify('09########'),
            'address' => fake()->address(),
            'license_file' => 'demo/licenses/'.fake()->uuid().'.pdf',
            'status' => 'pending',
        ];
    }

    public function approved(?User $approver = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $approver?->id,
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'rejected',
            'reject_reason' => $reason ?? 'Hồ sơ không hợp lệ.',
            'approved_by'   => null,
            'approved_at'   => null,
        ]);
    }

    public function suspended(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'suspended',
            'suspended_reason' => $reason ?? 'Vi phạm điều khoản sử dụng.',
        ]);
    }
}
