<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Support\PublicImage;

/*
 * GET /menu (public, API-CONTRACT §3; frontend services/menu.ts getMenu).
 */

it('returns the categories in order with their products, without a token', function () {
    $drinks = Category::factory()->create(['slug' => 'drinks', 'name' => 'مشروبات', 'position' => 2]);
    $sandwiches = Category::factory()->create(['slug' => 'sandwiches', 'name' => 'سندويشات', 'position' => 1, 'image_path' => 'uploads/s.webp']);
    $shawarma = Product::factory()->for($sandwiches)->create([
        'slug' => 'chicken-shawarma', 'name' => 'شاورما جاج', 'description' => 'وصف', 'ingredients' => ['ثوم'],
        'price' => 250, 'image_path' => 'uploads/p.webp', 'sizes_label' => 'الحجم',
    ]);
    $regular = ProductSize::factory()->for($shawarma)->create(['name' => 'عادي', 'price_delta' => 0, 'position' => 1]);
    $double = ProductSize::factory()->for($shawarma)->create(['name' => 'دبل', 'price_delta' => 100, 'position' => 2]);
    Product::factory()->for($drinks)->withoutImage()->create([
        'slug' => 'pepsi', 'name' => 'بيبسي', 'description' => '', 'ingredients' => [], 'price' => 50,
    ]);

    $response = $this->getJson('/api/v1/menu');

    $response->assertOk()->assertExactJson([
        [
            'category' => ['id' => 'sandwiches', 'name' => 'سندويشات', 'image' => PublicImage::url('uploads/s.webp'), 'order' => 1],
            'products' => [[
                'id' => 'chicken-shawarma', 'categoryId' => 'sandwiches', 'name' => 'شاورما جاج', 'description' => 'وصف',
                'ingredients' => ['ثوم'], 'price' => 250, 'image' => PublicImage::url('uploads/p.webp'),
                'isBestSeller' => false, 'isAvailable' => true,
                'optionGroups' => [[
                    'id' => 'size', 'name' => 'الحجم', 'required' => true, 'maxSelect' => 1,
                    'options' => [
                        ['id' => (string) $regular->id, 'name' => 'عادي', 'priceDelta' => 0],
                        ['id' => (string) $double->id, 'name' => 'دبل', 'priceDelta' => 100],
                    ],
                ]],
            ]],
        ],
        [
            'category' => ['id' => 'drinks', 'name' => 'مشروبات', 'order' => 2],
            'products' => [[
                'id' => 'pepsi', 'categoryId' => 'drinks', 'name' => 'بيبسي', 'description' => '',
                'ingredients' => [], 'price' => 50, 'isBestSeller' => false, 'isAvailable' => true,
            ]],
        ],
    ]);
});

it('leaves out categories without products', function () {
    Category::factory()->create(['slug' => 'empty']);
    Product::factory()->for(Category::factory()->create(['slug' => 'meals']))->create();

    $response = $this->getJson('/api/v1/menu');

    expect(collect($response->json())->pluck('category.id')->all())->toBe(['meals']);
});

it('includes unavailable products (the site shows them dimmed)', function () {
    Product::factory()->unavailable()->create(['slug' => 'sold-out']);

    $response = $this->getJson('/api/v1/menu');

    $response->assertJsonPath('0.products.0.id', 'sold-out')
        ->assertJsonPath('0.products.0.isAvailable', false);
});

it('lists the products of a category in the order they were created', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create(['slug' => 'first']);
    Product::factory()->for($category)->create(['slug' => 'second']);

    $response = $this->getJson('/api/v1/menu');

    expect(collect($response->json('0.products'))->pluck('id')->all())->toBe(['first', 'second']);
});

it('uses three queries whatever the size of the menu (no N+1)', function () {
    Category::factory()->count(4)->create()->each(function (Category $category) {
        Product::factory()->count(3)->for($category)->create()->each(
            fn (Product $product) => ProductSize::factory()->count(2)->for($product)->create(),
        );
    });

    $this->expectsDatabaseQueryCount(3);

    $this->getJson('/api/v1/menu')->assertOk();
});
