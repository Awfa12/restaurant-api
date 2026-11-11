<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state: a new category goes last.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'image_path' => null,
            'position' => fn () => (int) Category::max('position') + 1,
        ];
    }

    /**
     * A category with a card photo.
     */
    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => 'uploads/'.fake()->uuid().'.webp',
        ]);
    }
}
