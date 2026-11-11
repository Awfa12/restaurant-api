<?php

use App\Enums\BannerTargetKind;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Category;
use App\Models\OrderingSetting;
use App\Models\Phone;
use App\Models\Setting;
use App\Models\Upload;
use App\Rules\OwnUploadUrl;
use App\Support\ImageReferences;
use App\Support\PublicImage;
use Database\Seeders\Support\SeedData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/*
 * Phase 2: the seed data (database/seeders/data, exported from the frontend's
 * mock files) seeded as the restaurant's starting content. The expected
 * values are read from the same JSON, so the tests follow a re-export.
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * What the frontend's getMenu() builds from the mock: categories by order,
 * empty ones left out, each with its products in the mock's order.
 *
 * @return array<int, array<string, mixed>>
 */
function expectedMenuFromMock(): array
{
    $menu = SeedData::read('menu');

    $bestSellerPlaces = collect($menu['products'])
        ->map(fn (array $product, int $index) => [...$product, '_index' => $index])
        ->filter(fn (array $product) => $product['isBestSeller'])
        ->sortBy([fn ($a, $b) => ($a['bestSellerOrder'] ?? INF) <=> ($b['bestSellerOrder'] ?? INF), fn ($a, $b) => $a['_index'] <=> $b['_index']])
        ->values()
        ->mapWithKeys(fn (array $product, int $place) => [$product['id'] => $place + 1]);

    return collect($menu['categories'])->sortBy('order')->values()
        ->map(fn (array $category, int $index) => [
            'category' => ['id' => $category['id'], 'name' => $category['name'], 'hasImage' => isset($category['image']), 'order' => $index + 1],
            'products' => collect($menu['products'])->where('categoryId', $category['id'])->values()
                ->map(fn (array $product) => [
                    'id' => $product['id'],
                    'categoryId' => $product['categoryId'],
                    'name' => $product['name'],
                    'description' => $product['description'],
                    'ingredients' => $product['ingredients'],
                    'price' => $product['price'],
                    'hasImage' => isset($product['image']),
                    'imageStyle' => $product['imageStyle'] ?? null,
                    'isBestSeller' => $product['isBestSeller'],
                    'bestSellerOrder' => $bestSellerPlaces[$product['id']] ?? null,
                    'isAvailable' => $product['isAvailable'],
                    'sizesLabel' => $product['optionGroups'][0]['name'] ?? null,
                    'sizes' => collect($product['optionGroups'][0]['options'] ?? [])
                        ->map(fn (array $option) => [$option['name'], $option['shortName'] ?? null, $option['priceDelta']])->all(),
                ])->all(),
        ])
        ->filter(fn (array $section) => $section['products'] !== [])
        ->values()
        ->all();
}

/**
 * GET /menu in the same comparable shape (new size ids and our image URLs aside).
 *
 * @param  array<int, array<string, mixed>>  $sections
 * @return array<int, array<string, mixed>>
 */
function comparableMenu(array $sections): array
{
    return collect($sections)->map(fn (array $section) => [
        'category' => ['id' => $section['category']['id'], 'name' => $section['category']['name'], 'hasImage' => isset($section['category']['image']), 'order' => $section['category']['order']],
        'products' => collect($section['products'])->map(fn (array $product) => [
            'id' => $product['id'],
            'categoryId' => $product['categoryId'],
            'name' => $product['name'],
            'description' => $product['description'],
            'ingredients' => $product['ingredients'],
            'price' => $product['price'],
            'hasImage' => isset($product['image']),
            'imageStyle' => $product['imageStyle'] ?? null,
            'isBestSeller' => $product['isBestSeller'],
            'bestSellerOrder' => $product['bestSellerOrder'] ?? null,
            'isAvailable' => $product['isAvailable'],
            'sizesLabel' => $product['optionGroups'][0]['name'] ?? null,
            'sizes' => collect($product['optionGroups'][0]['options'] ?? [])
                ->map(fn (array $option) => [$option['name'], $option['shortName'] ?? null, $option['priceDelta']])->all(),
        ])->all(),
    ])->all();
}

it('serves the mock menu from GET /menu: same slugs, names, prices, sizes, order and best sellers', function () {
    $this->seed();

    $response = $this->getJson('/api/v1/menu');

    $response->assertOk();
    expect(comparableMenu($response->json()))->toBe(expectedMenuFromMock());
});

it('keeps the slugs that are printed or shared: branches, products, offers, categories', function () {
    $this->seed();

    expect(Branch::orderBy('position')->pluck('slug')->all())->toBe(collect(SeedData::read('site-info')['branches'])->pluck('id')->all())
        ->and(Branch::pluck('slug')->all())->toContain('qanawat-road', 'al-mehwari')
        ->and(Category::pluck('slug')->all())->toEqualCanonicalizing(collect(SeedData::read('menu')['categories'])->pluck('id')->all());
});

it('remaps every reference to the new phone, branch and category ids', function () {
    $this->seed();
    $siteInfo = SeedData::read('site-info');
    $ordering = SeedData::read('ordering');

    $deliveryMock = collect($siteInfo['phones'])->firstWhere('id', $ordering['delivery']['phoneId']);
    $delivery = OrderingSetting::sole()->deliveryPhone;
    expect($delivery->number)->toBe($deliveryMock['number'])
        ->and($delivery->branch_id)->toBeNull();

    foreach (collect($siteInfo['phones'])->whereNotNull('branchId') as $mockPhone) {
        $branch = Branch::where('slug', $mockPhone['branchId'])->sole();
        expect($branch->phones()->pluck('number')->all())->toContain($mockPhone['number']);
    }

    foreach ($ordering['dineIn']['branches'] as $setting) {
        expect(Branch::where('slug', $setting['branchId'])->sole())
            ->dine_in_enabled->toBe($setting['isEnabled'])
            ->table_count->toBe($setting['tableCount']);
    }

    foreach (SeedData::read('banners') as $index => $mockBanner) {
        $banner = Banner::where('position', $index + 1)->sole();
        $target = $mockBanner['action']['target'] ?? null;

        expect($banner->target_kind?->value)->toBe($target['kind'] ?? null);
        if (($target['kind'] ?? null) === BannerTargetKind::Category->value) {
            expect($banner->targetCategory->slug)->toBe($target['categoryId']);
        }
    }
});

it('seeds every settings row and the ordering settings', function () {
    $this->seed();

    expect(Setting::pluck('key')->sort()->values()->all())->toBe(['about', 'hero', 'offers_section', 'reviews_section', 'site_info'])
        ->and(OrderingSetting::count())->toBe(1)
        ->and(Setting::where('key', 'hero')->value('value')['headline'])->toBe(SeedData::read('hero')['headline']);
});

it('stores every seeded photo as one of our uploads, which OwnUploadUrl accepts and uploads:prune keeps', function () {
    $this->seed();
    $paths = ImageReferences::paths();

    expect($paths)->not->toBeEmpty();
    foreach ($paths as $path) {
        Storage::disk('public')->assertExists($path);
        expect(Upload::where('path', $path)->exists())->toBeTrue()
            ->and(Validator::make(['image' => PublicImage::url($path)], ['image' => [new OwnUploadUrl]])->passes())->toBeTrue();
    }

    $this->travel(25)->hours();
    $this->artisan('uploads:prune')->assertSuccessful();

    expect(Upload::count())->toBe($paths->count());
});

it('refuses a second run and leaves the content as it was', function () {
    $this->seed();
    $before = [Category::count(), Phone::count(), Upload::count()];

    expect(fn () => $this->seed())->toThrow(RuntimeException::class, 'Seeding refused: the database already has content');

    expect([Category::count(), Phone::count(), Upload::count()])->toBe($before);
});

it('refuses before writing anything when a single content table has rows', function () {
    Setting::create(['key' => 'hero', 'value' => ['headline' => 'edited by the owner']]);

    expect(fn () => $this->seed())->toThrow(RuntimeException::class, '(table `settings`)');

    expect(Category::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and(Setting::where('key', 'hero')->value('value'))->toBe(['headline' => 'edited by the owner']);
});
