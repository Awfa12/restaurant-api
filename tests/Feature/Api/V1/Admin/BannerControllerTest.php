<?php

use App\Enums\BannerTargetKind;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Support\PublicImage;

/*
 * Dashboard menu banner slides (API-CONTRACT §4 Banners, decisions 6.1 and
 * 7.1; rules and texts from the frontend's checkBannerInput).
 */

/**
 * A valid whole slide, as the dashboard's form sends it (BannerInput).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bannerInput(array $overrides = []): array
{
    return array_merge([
        'eyebrow' => 'عرض الأسبوع',
        'title' => "كل 4 شاورما\n+ وحدة مجاناً",
        'image' => ['src' => uploadedImageUrl(), 'alt' => 'كل 4 شاورما + وحدة مجاناً'],
        'action' => ['label' => 'شوف العروض', 'target' => ['kind' => 'offers']],
        'isActive' => true,
    ], $overrides);
}

it('returns 401 without a token', function (string $method, string $uri) {
    $banner = Banner::factory()->create();

    $response = $this->json($method, str_replace('{id}', (string) $banner->id, $uri), []);

    $response->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/admin/banners'],
    'create' => ['POST', '/api/v1/admin/banners'],
    'save' => ['PUT', '/api/v1/admin/banners/{id}'],
    'switch' => ['PATCH', '/api/v1/admin/banners/{id}'],
    'delete' => ['DELETE', '/api/v1/admin/banners/{id}'],
    'move' => ['POST', '/api/v1/admin/banners/{id}/move'],
]);

describe('index', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns every slide in order, inactive ones too, in the frontend shape', function () {
        $product = Product::factory()->create(['slug' => 'chicken-shawarma']);
        $second = Banner::factory()->inactive()->create(['position' => 2]);
        $first = Banner::factory()->targetingProduct($product)->create([
            'eyebrow' => 'الأكثر طلباً', 'title' => "فروج\nالشوارية", 'image_path' => 'uploads/a.webp',
            'image_alt' => 'فروج مشوي', 'action_label' => 'اطلب الآن', 'position' => 1,
        ]);

        $response = $this->getJson('/api/v1/admin/banners');

        $response->assertOk()->assertJsonCount(2)->assertJsonPath('0', [
            'id' => (string) $first->id,
            'eyebrow' => 'الأكثر طلباً',
            'title' => "فروج\nالشوارية",
            'image' => ['src' => PublicImage::url('uploads/a.webp'), 'alt' => 'فروج مشوي'],
            'action' => ['label' => 'اطلب الآن', 'target' => ['kind' => 'product', 'productId' => 'chicken-shawarma']],
            'isActive' => true,
        ])->assertJsonPath('1.id', (string) $second->id)
            ->assertJsonPath('1.isActive', false)
            ->assertJsonMissingPath('1.eyebrow')
            ->assertJsonMissingPath('1.action');
    });

    it('sends a deleted target with an empty id, so the dashboard shows it is gone', function (string $kind, string $key) {
        $target = $kind === 'category' ? Category::factory()->create() : Product::factory()->create();
        $kind === 'category'
            ? Banner::factory()->targetingCategory($target)->create()
            : Banner::factory()->targetingProduct($target)->create();
        $target->delete();

        $response = $this->getJson('/api/v1/admin/banners');

        $response->assertOk()->assertJsonPath('0.action.target', ['kind' => $kind, $key => '']);
    })->with([
        'category' => ['category', 'categoryId'],
        'product' => ['product', 'productId'],
    ]);
});

describe('store', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 201 with the new slide last in the order', function () {
        Banner::factory()->create(['position' => 1]);
        $input = bannerInput();

        $response = $this->postJson('/api/v1/admin/banners', $input);

        $response->assertCreated()->assertJson([
            'eyebrow' => 'عرض الأسبوع',
            'title' => "كل 4 شاورما\n+ وحدة مجاناً",
            'image' => $input['image'],
            'action' => ['label' => 'شوف العروض', 'target' => ['kind' => 'offers']],
            'isActive' => true,
        ]);
        $this->assertDatabaseHas('banners', ['id' => (int) $response->json('id'), 'position' => 2, 'target_kind' => 'offers']);
    });

    it('stores a button to a category or a product by its slug', function (string $kind, string $key, string $column) {
        $category = Category::factory()->create(['slug' => 'chicken']);
        $product = Product::factory()->for($category)->create(['slug' => 'chicken-shawarma']);
        $slug = $kind === 'category' ? 'chicken' : 'chicken-shawarma';

        $response = $this->postJson('/api/v1/admin/banners', bannerInput([
            'action' => ['label' => 'اطلب الآن', 'target' => ['kind' => $kind, $key => $slug]],
        ]));

        $response->assertCreated()->assertJsonPath('action.target', ['kind' => $kind, $key => $slug]);
        $this->assertDatabaseHas('banners', [$column => $kind === 'category' ? $category->id : $product->id]);
    })->with([
        'category' => ['category', 'categoryId', 'target_category_id'],
        'product' => ['product', 'productId', 'target_product_id'],
    ]);

    it('uses the title on one line as the photo alt text when none is sent', function () {
        $input = bannerInput();
        $input['image'] = ['src' => $input['image']['src']];

        $response = $this->postJson('/api/v1/admin/banners', $input);

        $response->assertCreated()->assertJsonPath('image.alt', 'كل 4 شاورما + وحدة مجاناً');
    });

    it('keeps one kind of line break in the title', function () {
        $response = $this->postJson('/api/v1/admin/banners', bannerInput(['title' => "فروج\r\nالشوارية"]));

        $response->assertCreated()->assertJsonPath('title', "فروج\nالشوارية");
    });

    it('returns 422 with the frontend messages on the contract keys', function (Closure $overrides, string $field, string $message) {
        Category::factory()->create(['slug' => 'empty-is-fine']);

        $response = $this->postJson('/api/v1/admin/banners', bannerInput($overrides()));

        $response->assertUnprocessable();
        expect($response->json('errors')[$field] ?? [])->toContain($message);
    })->with([
        'eyebrow too long' => [fn () => ['eyebrow' => str_repeat('ع', 25)], 'eyebrow', 'لازم يكون 24 حرف أو أقل'],
        'no title' => [fn () => ['title' => ''], 'title', 'اكتب العنوان'],
        'three lines' => [fn () => ['title' => "سطر\nسطر\nسطر"], 'title', 'العنوان سطرين بالكتير'],
        'a line too long' => [fn () => ['title' => str_repeat('ع', 23)], 'title', 'كل سطر 22 حرف أو أقل، لحتى يبيّن على الموبايل'],
        'no photo' => [fn () => ['image' => ['src' => '', 'alt' => 'x']], 'image', 'الشريحة بدها صورة'],
        'photo missing' => [fn () => ['image' => null], 'image', 'الشريحة بدها صورة'],
        'photo not ours' => [fn () => ['image' => ['src' => 'https://evil.example/x.webp', 'alt' => 'x']], 'image', 'الصورة مش من الصور المرفوعة، ارفعها من جديد'],
        'no button label' => [fn () => ['action' => ['label' => ' ', 'target' => ['kind' => 'offers']]], 'action.label', 'اكتب شو مكتوب على الزر'],
        'button label too long' => [fn () => ['action' => ['label' => str_repeat('ع', 17), 'target' => ['kind' => 'offers']]], 'action.label', 'نص الزر 16 حرف أو أقل'],
        'unknown category' => [fn () => ['action' => ['label' => 'اطلب', 'target' => ['kind' => 'category', 'categoryId' => 'nope']]], 'action.target', 'اختار القسم'],
        'gone product' => [fn () => ['action' => ['label' => 'اطلب', 'target' => ['kind' => 'product', 'productId' => '']]], 'action.target', 'اختار الصنف'],
        'unknown kind' => [fn () => ['action' => ['label' => 'اطلب', 'target' => ['kind' => 'link']]], 'action.target', 'اختار وين بياخد الزر'],
    ]);
});

describe('update', function () {
    beforeEach(fn () => actingAsAdmin());

    it('saves the whole slide and clears the eyebrow and button it does not send', function () {
        $banner = Banner::factory()->targetingOffers()->create(['eyebrow' => 'قديم', 'position' => 3]);
        $input = bannerInput(['eyebrow' => null, 'title' => 'جديد', 'isActive' => false]);
        unset($input['action']);

        $response = $this->putJson("/api/v1/admin/banners/{$banner->id}", $input);

        $response->assertOk()
            ->assertJsonPath('title', 'جديد')
            ->assertJsonPath('isActive', false)
            ->assertJsonMissingPath('eyebrow')
            ->assertJsonMissingPath('action');
        $this->assertDatabaseHas('banners', [
            'id' => $banner->id, 'eyebrow' => null, 'action_label' => null, 'target_kind' => null, 'position' => 3,
        ]);
    });

    it('moves the button from a category to the offers and drops the old key', function () {
        $banner = Banner::factory()->targetingCategory()->create();

        $this->putJson("/api/v1/admin/banners/{$banner->id}", bannerInput())->assertOk();

        expect($banner->refresh())
            ->target_kind->toBe(BannerTargetKind::Offers)
            ->target_category_id->toBeNull();
    });

    it('returns 404 for an unknown slide', function (string $id) {
        $this->putJson("/api/v1/admin/banners/{$id}", bannerInput())->assertNotFound();
    })->with(['unknown id' => '999', 'not an id' => 'chicken']);
});

describe('patch', function () {
    it('switches the slide on or off and ignores the other fields', function () {
        actingAsAdmin();
        $banner = Banner::factory()->create(['title' => 'قديم']);

        $response = $this->patchJson("/api/v1/admin/banners/{$banner->id}", ['isActive' => false, 'title' => 'غيره']);

        $response->assertOk()->assertJsonPath('isActive', false)->assertJsonPath('title', 'قديم');
        expect($banner->refresh()->is_active)->toBeFalse();
    });
});

describe('move and destroy', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->banners = collect([1, 2, 3])->map(fn (int $position) => Banner::factory()->create(['position' => $position]));
    });

    it('swaps the slide with its neighbour and returns every slide in the new order', function () {
        [$a, $b, $c] = $this->banners->all();

        $response = $this->postJson("/api/v1/admin/banners/{$a->id}/move", ['direction' => 'down']);

        $response->assertOk();
        expect(collect($response->json())->pluck('id')->all())->toBe([(string) $b->id, (string) $a->id, (string) $c->id]);
    });

    it('returns 204, deletes the slide and renumbers the rest with no gaps', function () {
        [$a, $b, $c] = $this->banners->all();

        $this->deleteJson("/api/v1/admin/banners/{$a->id}")->assertNoContent();

        expect(Banner::orderBy('position')->pluck('position', 'id')->all())->toBe([$b->id => 1, $c->id => 2]);
    });
});
