<?php

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Models\Upload;
use App\Support\PublicImage;

/*
 * Dashboard categories (API-CONTRACT §4 Categories, decision 4.1).
 */

it('returns 401 without a token', function (string $method, string $uri) {
    Category::factory()->create(['slug' => 'sandwiches']);

    $response = $this->json($method, $uri, ['name' => 'وجبات', 'direction' => 'up']);

    $response->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/admin/categories'],
    'create' => ['POST', '/api/v1/admin/categories'],
    'update' => ['PATCH', '/api/v1/admin/categories/sandwiches'],
    'delete' => ['DELETE', '/api/v1/admin/categories/sandwiches'],
    'move' => ['POST', '/api/v1/admin/categories/sandwiches/move'],
]);

describe('index', function () {
    it('returns every category in menu order, with the slug as id and the photo only when set', function () {
        actingAsAdmin();
        Category::factory()->create(['slug' => 'drinks', 'name' => 'مشروبات', 'position' => 2]);
        Category::factory()->create(['slug' => 'sandwiches', 'name' => 'سندويشات', 'position' => 1, 'image_path' => 'uploads/a.webp']);

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertOk()->assertExactJson([
            ['id' => 'sandwiches', 'name' => 'سندويشات', 'image' => PublicImage::url('uploads/a.webp'), 'order' => 1],
            ['id' => 'drinks', 'name' => 'مشروبات', 'order' => 2],
        ]);
    });
});

describe('store', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 201 with the new category last in the order and a neutral slug', function () {
        Category::factory()->create(['position' => 1]);

        $response = $this->postJson('/api/v1/admin/categories', ['name' => '  وجبات   عائلية ']);

        $response->assertCreated()
            ->assertJsonPath('name', 'وجبات عائلية')
            ->assertJsonPath('order', 2)
            ->assertJsonMissingPath('image');
        expect($response->json('id'))->toMatch('/^cat-[a-z0-9]{8}$/');
        $this->assertDatabaseHas('categories', ['slug' => $response->json('id'), 'name' => 'وجبات عائلية', 'position' => 2]);
    });

    it('keeps the slug the owner sent', function () {
        $response = $this->postJson('/api/v1/admin/categories', ['name' => 'وجبات', 'slug' => 'family-meals']);

        $response->assertCreated()->assertJsonPath('id', 'family-meals');
    });

    it('returns 422 with Arabic messages for an invalid name', function (mixed $name, string $message) {
        Category::factory()->create(['name' => 'وجبات عائلية']);

        $response = $this->postJson('/api/v1/admin/categories', ['name' => $name]);

        $response->assertUnprocessable()->assertJsonPath('errors.name', [$message]);
    })->with([
        'missing' => ['', 'اكتب اسم القسم'],
        'only spaces' => ['   ', 'اكتب اسم القسم'],
        'longer than 24' => [str_repeat('ب', 25), 'الاسم لازم يكون 24 حرف أو أقل'],
        'same name' => ['وجبات عائلية', 'في قسم بنفس الاسم'],
        // Outer spaces are trimmed by Laravel's TrimStrings; the inner ones are collapsed by the request.
        'same name with extra inner spaces' => [' وجبات    عائلية ', 'في قسم بنفس الاسم'],
    ]);

    it('returns 422 for a slug that is not lowercase Latin kebab-case, or already used', function (string $slug, string $message) {
        Category::factory()->create(['slug' => 'sandwiches']);

        $response = $this->postJson('/api/v1/admin/categories', ['name' => 'وجبات', 'slug' => $slug]);

        $response->assertUnprocessable()->assertJsonPath('errors.slug', [$message]);
    })->with([
        'capitals' => ['Meals', 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma'],
        'Arabic' => ['وجبات', 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma'],
        'double hyphen' => ['family--meals', 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma'],
        'slash' => ['meals/x', 'الرابط لازم يكون أحرف إنجليزية صغيرة وأرقام وشرطات، متل chicken-shawarma'],
        'already used' => ['sandwiches', 'هالرابط مستعمل، اختار غيره'],
    ]);
});

describe('update', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->category = Category::factory()->create(['slug' => 'sandwiches', 'name' => 'سندويشات', 'image_path' => 'uploads/old.webp']);
    });

    it('renames the category and leaves the rest as it was', function () {
        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['name' => 'سندويش']);

        $response->assertOk()
            ->assertJsonPath('id', 'sandwiches')
            ->assertJsonPath('name', 'سندويش')
            ->assertJsonPath('image', PublicImage::url('uploads/old.webp'));
    });

    it('accepts its own name again', function () {
        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['name' => 'سندويشات']);

        $response->assertOk();
    });

    it('returns 422 when the new name belongs to another category', function () {
        Category::factory()->create(['name' => 'وجبات']);

        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['name' => 'وجبات']);

        $response->assertUnprocessable()->assertJsonPath('errors.name', ['في قسم بنفس الاسم']);
    });

    it('changes the slug only when a new one is sent', function (?string $sent, string $expected) {
        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['slug' => $sent]);

        $response->assertOk()->assertJsonPath('id', $expected);
    })->with([
        'new slug' => ['wraps', 'wraps'],
        'empty: kept' => ['', 'sandwiches'],
        'null: kept' => [null, 'sandwiches'],
    ]);

    it('sets the photo from one of our uploads', function () {
        $upload = Upload::factory()->create();

        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['image' => PublicImage::url($upload->path)]);

        $response->assertOk()->assertJsonPath('image', PublicImage::url($upload->path));
        expect($this->category->refresh()->image_path)->toBe($upload->path);
    });

    it('removes the photo with image: null', function () {
        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['image' => null]);

        $response->assertOk()->assertJsonMissingPath('image');
        expect($this->category->refresh()->image_path)->toBeNull();
    });

    it('returns 422 for a photo that is not one of our uploads', function () {
        $response = $this->patchJson('/api/v1/admin/categories/sandwiches', ['image' => 'https://evil.example/x.webp']);

        $response->assertUnprocessable()->assertJsonPath('errors.image', ['الصورة مش من الصور المرفوعة، ارفعها من جديد']);
        expect($this->category->refresh()->image_path)->toBe('uploads/old.webp');
    });

    it('returns 404 for an unknown category', function () {
        $response = $this->patchJson('/api/v1/admin/categories/nope', ['name' => 'x']);

        $response->assertNotFound();
    });
});

describe('move', function () {
    beforeEach(function () {
        actingAsAdmin();
        Category::factory()->create(['slug' => 'sandwiches', 'position' => 1]);
        Category::factory()->create(['slug' => 'meals', 'position' => 2]);
        Category::factory()->create(['slug' => 'drinks', 'position' => 3]);
    });

    it('swaps the category with its neighbour and returns the whole list', function (string $slug, string $direction, array $expected) {
        $response = $this->postJson("/api/v1/admin/categories/{$slug}/move", ['direction' => $direction]);

        $response->assertOk();
        expect(collect($response->json())->pluck('id')->all())->toBe($expected)
            ->and(collect($response->json())->pluck('order')->all())->toBe([1, 2, 3]);
    })->with([
        'up' => ['meals', 'up', ['meals', 'sandwiches', 'drinks']],
        'down' => ['meals', 'down', ['sandwiches', 'drinks', 'meals']],
        'first up: unchanged' => ['sandwiches', 'up', ['sandwiches', 'meals', 'drinks']],
        'last down: unchanged' => ['drinks', 'down', ['sandwiches', 'meals', 'drinks']],
    ]);

    it('closes gaps in the order while moving', function () {
        Category::where('slug', 'drinks')->update(['position' => 9]);

        $response = $this->postJson('/api/v1/admin/categories/drinks/move', ['direction' => 'up']);

        expect(collect($response->json())->pluck('order')->all())->toBe([1, 2, 3])
            ->and(collect($response->json())->pluck('id')->all())->toBe(['sandwiches', 'drinks', 'meals']);
    });

    it('returns 422 for another direction', function () {
        $response = $this->postJson('/api/v1/admin/categories/meals/move', ['direction' => 'left']);

        $response->assertUnprocessable()->assertJsonPath('errors.direction', ['الاتجاه لازم يكون up أو down']);
    });
});

describe('destroy', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 204, deletes the category and renumbers the rest with no gaps', function () {
        Category::factory()->create(['slug' => 'sandwiches', 'position' => 1]);
        $meals = Category::factory()->create(['slug' => 'meals', 'position' => 2]);
        Category::factory()->create(['slug' => 'drinks', 'position' => 3]);

        $response = $this->deleteJson('/api/v1/admin/categories/meals');

        $response->assertNoContent();
        $this->assertModelMissing($meals);
        expect(Category::orderBy('position')->pluck('position', 'slug')->all())->toBe(['sandwiches' => 1, 'drinks' => 2]);
    });

    it('returns 409 with an Arabic message while the category has products', function () {
        $category = Category::factory()->create(['slug' => 'sandwiches']);
        Product::factory()->count(2)->for($category)->create();

        $response = $this->deleteJson('/api/v1/admin/categories/sandwiches');

        $response->assertConflict()
            ->assertExactJson(['message' => 'ما فيك تحذف قسم فيه أصناف (2). انقلها لقسم تاني أو احذفها أول.']);
        $this->assertModelExists($category);
    });

    it('keeps a banner pointing at the deleted category, without its target', function () {
        $category = Category::factory()->create(['slug' => 'drinks']);
        $banner = Banner::factory()->targetingCategory($category)->create();

        $this->deleteJson('/api/v1/admin/categories/drinks')->assertNoContent();

        expect($banner->refresh()->target_category_id)->toBeNull();
    });
});
