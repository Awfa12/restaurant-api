<?php

use App\Enums\BannerTargetKind;
use App\Enums\ImageStyle;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Offer;
use App\Models\OrderingSetting;
use App\Models\Phone;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Setting;
use Illuminate\Database\QueryException;

/*
 * The rules the schema itself enforces (docs/DATABASE.md §4). The actions in
 * later phases check first and answer 409 / 422; these keys are the backstop
 * when something bypasses them.
 */

it('refuses to delete a category that still has products', function () {
    $category = Category::factory()->create();
    Product::factory()->for($category)->create();

    expect(fn () => $category->delete())->toThrow(QueryException::class);

    $this->assertModelExists($category);
});

it('deletes a product\'s sizes with the product', function () {
    $product = Product::factory()->create();
    $size = ProductSize::factory()->for($product)->create();

    $product->delete();

    $this->assertModelMissing($size);
});

it('keeps a banner but clears its product target when the product is deleted', function () {
    $product = Product::factory()->create();
    $banner = Banner::factory()->targetingProduct($product)->create();

    $product->delete();

    $banner->refresh();
    expect($banner->target_product_id)->toBeNull()
        ->and($banner->target_kind)->toBe(BannerTargetKind::Product);
});

it('keeps a banner but clears its category target when the category is deleted', function () {
    $category = Category::factory()->create();
    $banner = Banner::factory()->targetingCategory($category)->create();

    $category->delete();

    $banner->refresh();
    expect($banner->target_category_id)->toBeNull()
        ->and($banner->target_kind)->toBe(BannerTargetKind::Category);
});

it('deletes a branch\'s staff phones with the branch and keeps the restaurant phones', function () {
    $branch = Branch::factory()->withTables()->create();
    $staffPhone = Phone::factory()->forBranch($branch)->create();
    $restaurantPhone = Phone::factory()->create();

    $branch->delete();

    $this->assertModelMissing($staffPhone);
    $this->assertModelExists($restaurantPhone);
});

it('clears the delivery phone when that phone is deleted', function () {
    $phone = Phone::factory()->create();
    $ordering = OrderingSetting::factory()->withDelivery($phone)->create();

    $phone->delete();

    expect($ordering->refresh()->delivery_phone_id)->toBeNull();
});

it('refuses a slug already used in the same table', function (string $model) {
    $model::factory()->create(['slug' => 'chicken-shawarma']);

    expect(fn () => $model::factory()->create(['slug' => 'chicken-shawarma']))
        ->toThrow(QueryException::class);
})->with([
    'categories' => Category::class,
    'products' => Product::class,
    'offers' => Offer::class,
    'branches' => Branch::class,
]);

it('reads back lists, enums and Arabic JSON as they were saved', function () {
    $product = Product::factory()->photo()->create(['ingredients' => ['دجاج متبّل', 'ثوم']]);
    Setting::create(['key' => 'hero', 'value' => ['headline' => 'شاورما **أهل الكرم**']]);

    $product = Product::find($product->id);
    $hero = Setting::where('key', 'hero')->first();

    expect($product->ingredients)->toBe(['دجاج متبّل', 'ثوم'])
        ->and($product->image_style)->toBe(ImageStyle::Photo)
        ->and($hero->value)->toBe(['headline' => 'شاورما **أهل الكرم**']);
});
