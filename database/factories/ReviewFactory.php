<?php

namespace Database\Factories;

use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state: a visible review with the default question.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->firstName(),
            'question' => null,
            'answer' => fake()->sentence(),
            'time' => null,
            'is_visible' => true,
            'position' => fn () => (int) Review::max('position') + 1,
        ];
    }

    /**
     * A review hidden from the public site.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_visible' => false,
        ]);
    }
}
