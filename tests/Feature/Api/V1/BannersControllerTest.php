<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Support\PublicImage;

/*
 * GET /banners (public, API-CONTRACT §3, decision 7.1; frontend
 * services/menu.ts getMenuBanners). The button's target is sent as stored;
 * one that leads nowhere on the site is left out, the slide stays.
 */

beforeEach(function () {
    storeOffersSection();
    Offer::factory()->create();
});

it('returns the active slides in order with their stored targets, without a token', function () {
    $category = Category::factory()->create(['slug' => 'chicken']);
    Product::factory()->for($category)->create();
    Banner::factory()->inactive()->create(['position' => 1]);
    $offers = Banner::factory()->targetingOffers()->create([
        'eyebrow' => 'عرض الأسبوع', 'title' => "كل 4 شاورما\n+ وحدة مجاناً", 'image_path' => 'uploads/a.webp',
        'image_alt' => 'سندويشات شاورما', 'action_label' => 'شوف العروض', 'position' => 2,
    ]);
    $chicken = Banner::factory()->targetingCategory($category)->create(['position' => 3]);

    $response = $this->getJson('/api/v1/banners');

    $response->assertOk()->assertJsonCount(2)->assertJsonPath('0', [
        'id' => (string) $offers->id,
        'eyebrow' => 'عرض الأسبوع',
        'title' => "كل 4 شاورما\n+ وحدة مجاناً",
        'image' => ['src' => PublicImage::url('uploads/a.webp'), 'alt' => 'سندويشات شاورما'],
        'action' => ['label' => 'شوف العروض', 'target' => ['kind' => 'offers']],
        'isActive' => true,
    ])->assertJsonPath('1.id', (string) $chicken->id)
        ->assertJsonPath('1.action.target', ['kind' => 'category', 'categoryId' => 'chicken']);
});

it('leaves out a button whose target is not on the site, and keeps the slide', function (Closure $makeBanner) {
    $makeBanner();

    $response = $this->getJson('/api/v1/banners');

    $response->assertOk()->assertJsonCount(1)->assertJsonMissingPath('0.action');
})->with([
    'deleted category' => fn () => tap(Banner::factory()->targetingCategory()->create(), fn (Banner $banner) => $banner->targetCategory->delete()),
    'category without products (not on the menu)' => fn () => Banner::factory()->targetingCategory(Category::factory()->create())->create(),
    'deleted product' => fn () => tap(Banner::factory()->targetingProduct()->create(), fn (Banner $banner) => $banner->targetProduct->delete()),
    'offers section off' => fn () => [storeOffersSection(['isEnabled' => false]), Banner::factory()->targetingOffers()->create()],
    'no active offer' => fn () => [Offer::query()->update(['is_active' => false]), Banner::factory()->targetingOffers()->create()],
]);

it('keeps a button to a product even when it is unavailable (the menu still shows it)', function () {
    Banner::factory()->targetingProduct(Product::factory()->unavailable()->create(['slug' => 'pepsi']))->create();

    $response = $this->getJson('/api/v1/banners');

    $response->assertJsonPath('0.action.target', ['kind' => 'product', 'productId' => 'pepsi']);
});

it('uses a fixed number of queries whatever the number of slides (no N+1)', function () {
    $categories = Category::factory()->count(3)->create()->each(fn (Category $category) => Product::factory()->for($category)->create());
    $categories->each(fn (Category $category) => Banner::factory()->targetingCategory($category)->create());
    Product::all()->each(fn (Product $product) => Banner::factory()->targetingProduct($product)->create());
    Banner::factory()->count(3)->targetingOffers()->create();

    // Offers section + active offer check, the banners, their categories (with product counts), their products.
    $this->expectsDatabaseQueryCount(5);

    $this->getJson('/api/v1/banners')->assertOk()->assertJsonCount(9);
});
