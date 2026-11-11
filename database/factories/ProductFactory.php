<?php

namespace Database\Factories;

use App\Enums\ImageStyle;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state: an available product with a photo,
     * no sizes, not a best seller.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'category_id' => Category::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'ingredients' => fake()->words(3),
            'price' => fake()->numberBetween(1, 40) * 50,
            'image_path' => 'uploads/'.fake()->uuid().'.webp',
            'image_style' => null,
            'is_available' => true,
            'is_best_seller' => false,
            'best_seller_order' => null,
            'sizes_label' => null,
        ];
    }

    /**
     * A best seller at the given place in the home slider.
     */
    public function bestSeller(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'is_best_seller' => true,
            'best_seller_order' => $order,
        ]);
    }

    /**
     * A product without a photo (the menu shows a placeholder).
     */
    public function withoutImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => null,
            'image_style' => null,
        ]);
    }

    /**
     * A full photo with its own background.
     */
    public function photo(): static
    {
        return $this->state(fn (array $attributes) => [
            'image_style' => ImageStyle::Photo,
        ]);
    }

    /**
     * A product the restaurant has run out of (shown dimmed).
     */
    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
