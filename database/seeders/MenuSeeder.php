<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\Support\SeedData;
use Database\Seeders\Support\SeedImages;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Categories, products, their sizes and the best sellers, from menu.json
 * (frontend src/data/mock/menu.ts). Called by DatabaseSeeder only.
 */
class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SeedImages $images): void
    {
        $menu = SeedData::read('menu');

        $categories = collect($menu['categories'])->sortBy('order')->values();
        foreach ($categories as $index => $category) {
            Category::create([
                'slug' => $category['id'],
                'name' => $category['name'],
                'image_path' => isset($category['image']) ? $images->path($category['image']) : null,
                'position' => $index + 1,
            ]);
        }

        $categoryIds = Category::pluck('id', 'slug');

        // Created in the mock's order: a category lists its products by creation.
        foreach ($menu['products'] as $item) {
            $group = $this->sizeGroup($item);

            $product = Product::create([
                'slug' => $item['id'],
                'category_id' => $categoryIds[$item['categoryId']] ?? throw new RuntimeException("Product {$item['id']}: unknown category {$item['categoryId']}."),
                'name' => $item['name'],
                'description' => $item['description'],
                'ingredients' => $item['ingredients'],
                'price' => $item['price'],
                'image_path' => isset($item['image']) ? $images->path($item['image']) : null,
                'image_style' => $item['imageStyle'] ?? null,
                'is_available' => $item['isAvailable'],
                'is_best_seller' => $item['isBestSeller'],
                'best_seller_order' => null,
                'sizes_label' => $group['name'] ?? null,
            ]);

            // The mock shares one size group between products: each product gets its own rows (new ids).
            foreach ($group['options'] ?? [] as $index => $option) {
                $product->sizes()->create([
                    'name' => $option['name'],
                    'short_name' => $option['shortName'] ?? null,
                    'price_delta' => $option['priceDelta'],
                    'position' => $index + 1,
                ]);
            }
        }

        $this->numberBestSellers($menu['products']);
    }

    /**
     * The product's one size group, or null. The schema stores one single-choice
     * group per product (decision 1.4): anything else in the mock stops the seeding.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    protected function sizeGroup(array $item): ?array
    {
        $groups = $item['optionGroups'] ?? [];

        if ($groups === []) {
            return null;
        }

        if (count($groups) > 1 || ($groups[0]['maxSelect'] ?? 1) !== 1) {
            throw new RuntimeException("Product {$item['id']}: only one single-choice size group can be stored.");
        }

        return $groups[0];
    }

    /**
     * Best sellers 1…n in the frontend's sortBestSellers order: by
     * bestSellerOrder, missing ones last, then the mock's order.
     *
     * @param  array<int, array<string, mixed>>  $products
     */
    protected function numberBestSellers(array $products): void
    {
        $ordered = collect($products)
            ->map(fn (array $item, int $index) => ['slug' => $item['id'], 'order' => $item['bestSellerOrder'] ?? INF, 'index' => $index, 'isBestSeller' => $item['isBestSeller']])
            ->filter(fn (array $entry) => $entry['isBestSeller'])
            ->sortBy([['order', 'asc'], ['index', 'asc']])
            ->values();

        foreach ($ordered as $place => $entry) {
            Product::where('slug', $entry['slug'])->update(['best_seller_order' => $place + 1]);
        }
    }
}
