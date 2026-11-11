<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state: a branch without table orders.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'address' => fake()->streetAddress(),
            'map_url' => null,
            'dine_in_enabled' => false,
            'table_count' => 0,
            'position' => fn () => (int) Branch::max('position') + 1,
        ];
    }

    /**
     * A branch taking table orders.
     */
    public function withTables(int $tableCount = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'dine_in_enabled' => true,
            'table_count' => $tableCount,
        ]);
    }
}
