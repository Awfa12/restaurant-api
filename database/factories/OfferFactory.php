<?php

namespace Database\Factories;

use App\Models\Offer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Offer>
 */
class OfferFactory extends Factory
{
    /**
     * Define the model's default state: an active offer, last in the list.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->words(2, true),
            'items' => fake()->words(3),
            'free_item' => fake()->word(),
            'price' => fake()->numberBetween(10, 60) * 50,
            'image_path' => 'uploads/'.fake()->uuid().'.webp',
            'image_style' => null,
            'badge' => null,
            'is_active' => true,
            'position' => fn () => (int) Offer::max('position') + 1,
        ];
    }

    /**
     * An offer switched off (hidden from the public site).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
