<?php

namespace Database\Factories;

use App\Enums\BannerTargetKind;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    /**
     * Define the model's default state: an active slide without a button.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eyebrow' => null,
            'title' => fake()->words(3, true),
            'image_path' => 'uploads/'.fake()->uuid().'.webp',
            'image_alt' => fake()->words(2, true),
            'action_label' => null,
            'target_kind' => null,
            'target_category_id' => null,
            'target_product_id' => null,
            'is_active' => true,
            'position' => fn () => (int) Banner::max('position') + 1,
        ];
    }

    /**
     * A button that goes to the offers section.
     */
    public function targetingOffers(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_label' => fake()->words(2, true),
            'target_kind' => BannerTargetKind::Offers,
        ]);
    }

    /**
     * A button that goes to a category.
     */
    public function targetingCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action_label' => fake()->words(2, true),
            'target_kind' => BannerTargetKind::Category,
            'target_category_id' => $category ?? Category::factory(),
        ]);
    }

    /**
     * A button that goes to a product page.
     */
    public function targetingProduct(?Product $product = null): static
    {
        return $this->state(fn (array $attributes) => [
            'action_label' => fake()->words(2, true),
            'target_kind' => BannerTargetKind::Product,
            'target_product_id' => $product ?? Product::factory(),
        ]);
    }
}
