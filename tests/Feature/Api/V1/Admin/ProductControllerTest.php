<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Upload;
use App\Support\PublicImage;

/*
 * Dashboard products (API-CONTRACT §4 Products, decisions 4.1 and 4.2;
 * rules and texts from the frontend's checkProductInput / checkProductQuickChanges).
 */

/**
 * The URL of a stored upload, as POST /admin/uploads returns it.
 */
function uploadedImageUrl(): string
{
    return PublicImage::url(Upload::factory()->create()->path);
}

/**
 * A valid whole product, as the dashboard's form sends it (ProductInput).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productInput(array $overrides = []): array
{
    Category::firstOrCreate(['slug' => 'sandwiches'], ['name' => 'سندويشات', 'position' => 1]);

    return array_merge([
        'categoryId' => 'sandwiches',
        'name' => 'شاورما جاج',
        'description' => 'شاورما دجاج متبّل على السيخ.',
        'ingredients' => ['دجاج متبّل', 'ثوم'],
        'price' => 250,
        'image' => uploadedImageUrl(),
        'isBestSeller' => false,
        'isAvailable' => true,
    ], $overrides);
}

/**
 * The size group as the dashboard builds it (product-form.ts sizeGroup).
 *
 * @param  array<int, array<string, mixed>>  $options
 * @return array<int, array<string, mixed>>
 */
function sizeGroup(array $options): array
{
    return [['id' => 'size', 'name' => 'الحجم', 'required' => true, 'maxSelect' => 1, 'options' => $options]];
}

it('returns 401 without a token', function (string $method, string $uri) {
    Product::factory()->create(['slug' => 'chicken-shawarma']);

    $response = $this->json($method, $uri, []);

    $response->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/admin/products'],
    'create' => ['POST', '/api/v1/admin/products'],
    'save' => ['PUT', '/api/v1/admin/products/chicken-shawarma'],
    'quick change' => ['PATCH', '/api/v1/admin/products/chicken-shawarma'],
    'delete' => ['DELETE', '/api/v1/admin/products/chicken-shawarma'],
]);

describe('index', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns every product in the frontend shape, sizes as one group', function () {
        $category = Category::factory()->create(['slug' => 'sandwiches']);
        $product = Product::factory()->for($category)->photo()->bestSeller(1)->create([
            'slug' => 'chicken-shawarma', 'name' => 'شاورما جاج', 'description' => 'وصف', 'ingredients' => ['ثوم'],
            'price' => 250, 'image_path' => 'uploads/a.webp', 'sizes_label' => 'الحجم',
        ]);
        $regular = ProductSize::factory()->for($product)->create(['name' => 'عادي', 'price_delta' => 0, 'position' => 1]);
        $double = ProductSize::factory()->for($product)->create(['name' => 'دبل بخبز السمون', 'short_name' => 'دبل سمون', 'price_delta' => 150, 'position' => 2]);

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertOk()->assertExactJson([[
            'id' => 'chicken-shawarma',
            'categoryId' => 'sandwiches',
            'name' => 'شاورما جاج',
            'description' => 'وصف',
            'ingredients' => ['ثوم'],
            'price' => 250,
            'image' => PublicImage::url('uploads/a.webp'),
            'imageStyle' => 'photo',
            'isBestSeller' => true,
            'bestSellerOrder' => 1,
            'isAvailable' => true,
            'optionGroups' => [[
                'id' => 'size', 'name' => 'الحجم', 'required' => true, 'maxSelect' => 1,
                'options' => [
                    ['id' => (string) $regular->id, 'name' => 'عادي', 'priceDelta' => 0],
                    ['id' => (string) $double->id, 'name' => 'دبل بخبز السمون', 'shortName' => 'دبل سمون', 'priceDelta' => 150],
                ],
            ]],
        ]]);
    });

    it('leaves out the optional fields a product does not have', function () {
        Product::factory()->withoutImage()->unavailable()->create();

        $response = $this->getJson('/api/v1/admin/products');

        $response->assertOk()
            ->assertJsonMissingPath('0.image')
            ->assertJsonMissingPath('0.imageStyle')
            ->assertJsonMissingPath('0.bestSellerOrder')
            ->assertJsonMissingPath('0.optionGroups')
            ->assertJsonPath('0.isAvailable', false);
    });
});

describe('store', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 201 with the saved product and a neutral slug', function () {
        $input = productInput(['optionGroups' => sizeGroup([
            ['id' => '', 'name' => 'عادي', 'priceDelta' => 0],
            ['id' => '', 'name' => 'دبل', 'priceDelta' => 100],
        ])]);

        $response = $this->postJson('/api/v1/admin/products', $input);

        $response->assertCreated()
            ->assertJsonPath('name', 'شاورما جاج')
            ->assertJsonPath('categoryId', 'sandwiches')
            ->assertJsonPath('image', $input['image'])
            ->assertJsonPath('optionGroups.0.options.1.name', 'دبل');
        expect($response->json('id'))->toMatch('/^item-[a-z0-9]{8}$/');
        $this->assertDatabaseCount('product_sizes', 2);
    });

    it('keeps the slug the owner sent', function () {
        $response = $this->postJson('/api/v1/admin/products', productInput(['slug' => 'chicken-shawarma']));

        $response->assertCreated()->assertJsonPath('id', 'chicken-shawarma');
    });

    it('returns 422 for a slug already used by a product or an offer (one URL space)', function (string $owner) {
        $owner === 'product'
            ? Product::factory()->create(['slug' => 'chicken-cola'])
            : Offer::factory()->create(['slug' => 'chicken-cola']);

        $response = $this->postJson('/api/v1/admin/products', productInput(['slug' => 'chicken-cola']));

        $response->assertUnprocessable()->assertJsonPath('errors.slug', ['هالرابط مستعمل، اختار غيره']);
    })->with(['product', 'offer']);

    it('puts a new best seller last in the slider', function () {
        Product::factory()->bestSeller(1)->create();
        Product::factory()->bestSeller(2)->create();

        $response = $this->postJson('/api/v1/admin/products', productInput(['isBestSeller' => true]));

        $response->assertCreated()->assertJsonPath('bestSellerOrder', 3);
    });

    it('returns 422 with the frontend\'s Arabic message and key', function (array $overrides, string $field, string $message) {
        $response = $this->postJson('/api/v1/admin/products', productInput($overrides));

        // Keys like "optionGroups.0.options.1.name" contain dots, so read them directly, not with assertJsonPath.
        $response->assertUnprocessable();
        expect($response->json('errors')[$field] ?? null)->toBe([$message]);
        $this->assertDatabaseCount('products', 0);
    })->with([
        'no name' => [['name' => ''], 'name', 'اكتب اسم الصنف'],
        'name > 40' => [['name' => str_repeat('ش', 41)], 'name', 'الاسم لازم يكون 40 حرف أو أقل'],
        'price not a number' => [['price' => 'abc'], 'price', 'السعر لازم يكون رقم، متل 250'],
        'price zero' => [['price' => 0], 'price', 'السعر لازم يكون أكبر من صفر'],
        'price too big' => [['price' => 10_000_001], 'price', 'السعر كبير كتير، تأكد منه'],
        'unknown category' => [['categoryId' => 'nope'], 'categoryId', 'اختار قسم للصنف'],
        'description > 160' => [['description' => str_repeat('و', 161)], 'description', 'الوصف لازم يكون 160 حرف أو أقل'],
        '11 ingredients' => [['ingredients' => array_fill(0, 11, 'ثوم')], 'ingredients', 'أقصى شي 10 مكونات (أكتر من هيك ما بيبيّنوا منيح عالموبايل)'],
        'ingredient > 30' => [['ingredients' => [str_repeat('ث', 31)]], 'ingredients', 'كل مكوّن لازم يكون 30 حرف أو أقل'],
        'best seller without a photo' => [['isBestSeller' => true, 'image' => null], 'isBestSeller', '«الأكثر مبيعاً» بدّه صورة للصنف'],
        'photo not ours' => [['image' => 'https://evil.example/x.webp'], 'image', 'الصورة مش من الصور المرفوعة، ارفعها من جديد'],
        'one size only' => [['optionGroups' => sizeGroup([['id' => '', 'name' => 'عادي', 'priceDelta' => 0]])], 'optionGroups', 'الأحجام لازم تكون حجمين أو أكتر، أو شيلها'],
        'size without a name' => [['optionGroups' => sizeGroup([['id' => '', 'name' => ' ', 'priceDelta' => 0], ['id' => '', 'name' => 'دبل', 'priceDelta' => 100]])], 'optionGroups.0.options.0.name', 'اكتب اسم الحجم'],
        'size name > 24' => [['optionGroups' => sizeGroup([['id' => '', 'name' => str_repeat('د', 25), 'priceDelta' => 0], ['id' => '', 'name' => 'دبل', 'priceDelta' => 100]])], 'optionGroups.0.options.0.name', 'الاسم طويل'],
        'two sizes with one name' => [['optionGroups' => sizeGroup([['id' => '', 'name' => 'دبل', 'priceDelta' => 0], ['id' => '', 'name' => 'دبل', 'priceDelta' => 100]])], 'optionGroups.0.options.1.name', 'في حجم بنفس الاسم'],
        'short name > 10' => [['optionGroups' => sizeGroup([['id' => '', 'name' => 'عادي', 'shortName' => str_repeat('ع', 11), 'priceDelta' => 0], ['id' => '', 'name' => 'دبل', 'priceDelta' => 100]])], 'optionGroups.0.options.0.shortName', 'الاسم المختصر 10 حروف أو أقل'],
        'size price ≤ 0' => [['optionGroups' => sizeGroup([['id' => '', 'name' => 'صغير', 'priceDelta' => -250], ['id' => '', 'name' => 'دبل', 'priceDelta' => 100]])], 'optionGroups.0.options.0.priceDelta', 'سعر الحجم لازم يكون رقم أكبر من صفر'],
    ]);

    it('refuses a size id sent with a new product', function () {
        $otherSize = ProductSize::factory()->create();

        $response = $this->postJson('/api/v1/admin/products', productInput(['optionGroups' => sizeGroup([
            ['id' => (string) $otherSize->id, 'name' => 'عادي', 'priceDelta' => 0],
            ['id' => '', 'name' => 'دبل', 'priceDelta' => 100],
        ])]));

        $response->assertUnprocessable()->assertJsonPath('errors', ['optionGroups.0.options.0.id' => ['هالحجم مش تابع لهالصنف، حدّث الصفحة']]);
    });
});

describe('update (PUT, whole product)', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->product = Product::factory()->photo()->create(['slug' => 'chicken-shawarma', 'sizes_label' => 'الحجم']);
        $this->regular = ProductSize::factory()->for($this->product)->create(['name' => 'عادي', 'price_delta' => 0, 'position' => 1]);
        $this->double = ProductSize::factory()->for($this->product)->create(['name' => 'دبل', 'price_delta' => 100, 'position' => 2]);
    });

    it('clears the optional fields the form leaves out (photo, style, sizes)', function () {
        $input = productInput();
        unset($input['image']);

        $response = $this->putJson('/api/v1/admin/products/chicken-shawarma', $input);

        $response->assertOk()
            ->assertJsonMissingPath('image')
            ->assertJsonMissingPath('imageStyle')
            ->assertJsonMissingPath('optionGroups');
        expect($this->product->refresh())
            ->image_path->toBeNull()
            ->image_style->toBeNull()
            ->sizes_label->toBeNull();
        $this->assertDatabaseCount('product_sizes', 0);
    });

    it('saves sizes by id: kept ids updated, empty id created, missing id deleted', function () {
        $response = $this->putJson('/api/v1/admin/products/chicken-shawarma', productInput(['optionGroups' => sizeGroup([
            ['id' => (string) $this->double->id, 'name' => 'دبل', 'priceDelta' => 120],
            ['id' => '', 'name' => 'تريبل', 'priceDelta' => 200],
        ])]));

        $response->assertOk();
        $this->assertModelMissing($this->regular);
        expect($this->double->refresh())->price_delta->toBe(120)->position->toBe(1)
            ->and($response->json('optionGroups.0.options.0.id'))->toBe((string) $this->double->id)
            ->and($response->json('optionGroups.0.options.1.name'))->toBe('تريبل');
    });

    it('returns 422 and changes nothing when a size id belongs to another product', function () {
        $otherSize = ProductSize::factory()->create(['name' => 'غريب', 'price_delta' => 10]);

        $response = $this->putJson('/api/v1/admin/products/chicken-shawarma', productInput(['optionGroups' => sizeGroup([
            ['id' => (string) $otherSize->id, 'name' => 'مسروق', 'priceDelta' => 0],
            ['id' => (string) $this->double->id, 'name' => 'دبل', 'priceDelta' => 100],
        ])]));

        $response->assertUnprocessable()->assertJsonPath('errors', ['optionGroups.0.options.0.id' => ['هالحجم مش تابع لهالصنف، حدّث الصفحة']]);
        expect($otherSize->refresh()->name)->toBe('غريب');
        $this->assertModelExists($this->regular);
    });

    it('keeps the slug when none is sent and changes it when a new one is', function (?string $sent, string $expected) {
        $response = $this->putJson('/api/v1/admin/products/chicken-shawarma', productInput(['slug' => $sent]));

        $response->assertOk()->assertJsonPath('id', $expected);
    })->with([
        'none' => [null, 'chicken-shawarma'],
        'new' => ['chicken-wrap', 'chicken-wrap'],
    ]);

    it('keeps its place in the slider', function () {
        Product::factory()->bestSeller(1)->create();
        $this->product->update(['is_best_seller' => true, 'best_seller_order' => 2]);
        Product::factory()->bestSeller(3)->create();

        $response = $this->putJson('/api/v1/admin/products/chicken-shawarma', productInput(['isBestSeller' => true, 'bestSellerOrder' => 1]));

        $response->assertOk()->assertJsonPath('bestSellerOrder', 2);
    });

    it('returns 404 for an unknown product', function () {
        $response = $this->putJson('/api/v1/admin/products/nope', productInput());

        $response->assertNotFound();
    });
});

describe('patch (quick change from the list)', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->first = Product::factory()->bestSeller(1)->create(['slug' => 'first']);
        $this->product = Product::factory()->create(['slug' => 'chicken-shawarma', 'name' => 'شاورما جاج', 'price' => 250]);
        $this->third = Product::factory()->bestSeller(2)->create(['slug' => 'third']);
    });

    it('changes only the fields sent', function () {
        $response = $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['price' => 300, 'name' => 'ignored']);

        $response->assertOk()
            ->assertJsonPath('price', 300)
            ->assertJsonPath('name', 'شاورما جاج')
            ->assertJsonPath('isAvailable', true)
            ->assertJsonPath('image', PublicImage::url($this->product->image_path));
    });

    it('turns availability off', function () {
        $response = $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['isAvailable' => false]);

        $response->assertOk()->assertJsonPath('isAvailable', false);
    });

    it('puts a new best seller last, and renumbers when one stops', function () {
        $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['isBestSeller' => true])
            ->assertOk()->assertJsonPath('bestSellerOrder', 3);

        $this->patchJson('/api/v1/admin/products/first', ['isBestSeller' => false])
            ->assertOk()->assertJsonMissingPath('bestSellerOrder');

        expect(Product::where('is_best_seller', true)->orderBy('best_seller_order')->pluck('best_seller_order', 'slug')->all())
            ->toBe(['third' => 1, 'chicken-shawarma' => 2])
            ->and($this->first->refresh()->best_seller_order)->toBeNull();
    });

    it('puts a best seller back in its old place on undo (fractional place)', function () {
        $response = $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['isBestSeller' => true, 'bestSellerOrder' => 1.5]);

        $response->assertOk()->assertJsonPath('bestSellerOrder', 2);
        expect($this->third->refresh()->best_seller_order)->toBe(3);
    });

    it('returns 422 with the quick-change text for a best seller without a photo', function () {
        Product::factory()->withoutImage()->create(['slug' => 'no-photo']);

        $response = $this->patchJson('/api/v1/admin/products/no-photo', ['isBestSeller' => true]);

        $response->assertUnprocessable()->assertJsonPath('errors.isBestSeller', ['«الأكثر مبيعاً» بدّه صورة للصنف. ضيف صورة أول.']);
    });

    it('returns 422 for an invalid price', function () {
        $response = $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['price' => 0]);

        $response->assertUnprocessable()->assertJsonPath('errors.price', ['السعر لازم يكون أكبر من صفر']);
    });

    it('returns 422 when the new price would make a size cost zero or less', function () {
        ProductSize::factory()->for($this->product)->create(['price_delta' => -200]);

        $response = $this->patchJson('/api/v1/admin/products/chicken-shawarma', ['price' => 200]);

        $response->assertUnprocessable()->assertJsonPath('errors.price', ['في حجم سعره بيصير صفر أو أقل بهالسعر، عدّل الأحجام من صفحة الصنف']);
    });
});

describe('destroy', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 204 and takes the sizes with it, clears banner targets and renumbers the best sellers', function () {
        $first = Product::factory()->bestSeller(1)->create(['slug' => 'first']);
        $second = Product::factory()->bestSeller(2)->create(['slug' => 'second']);
        $size = ProductSize::factory()->for($first)->create();
        $banner = Banner::factory()->targetingProduct($first)->create();

        $response = $this->deleteJson('/api/v1/admin/products/first');

        $response->assertNoContent();
        $this->assertModelMissing($first);
        $this->assertModelMissing($size);
        expect($banner->refresh()->target_product_id)->toBeNull()
            ->and($second->refresh()->best_seller_order)->toBe(1);
    });
});
