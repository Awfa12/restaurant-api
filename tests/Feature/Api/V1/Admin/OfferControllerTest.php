<?php

use App\Models\Offer;
use App\Models\Product;
use App\Support\PublicImage;

/*
 * Dashboard offers (API-CONTRACT §2 PUT/PATCH, §4 Offers; rules and texts from
 * the frontend's checkOfferInput).
 */

/**
 * A valid whole offer, as the dashboard's form sends it (OfferInput).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function offerInput(array $overrides = []): array
{
    return array_merge([
        'title' => 'عرض الفروج',
        'items' => ['فروج مشوي', 'بطاطا', 'ثوم'],
        'freeItem' => 'لتر ببسي',
        'price' => 1250,
        'image' => uploadedImageUrl(),
        'isActive' => true,
    ], $overrides);
}

it('returns 401 without a token', function (string $method, string $uri) {
    Offer::factory()->create(['slug' => 'chicken-cola']);

    $response = $this->json($method, $uri, []);

    $response->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/admin/offers'],
    'create' => ['POST', '/api/v1/admin/offers'],
    'save' => ['PUT', '/api/v1/admin/offers/chicken-cola'],
    'switch' => ['PATCH', '/api/v1/admin/offers/chicken-cola'],
    'delete' => ['DELETE', '/api/v1/admin/offers/chicken-cola'],
    'move' => ['POST', '/api/v1/admin/offers/chicken-cola/move'],
    'section' => ['PATCH', '/api/v1/admin/offers-section'],
]);

describe('index', function () {
    it('returns the section settings flat with every offer, inactive ones too, in order', function () {
        actingAsAdmin();
        storeOffersSection(['isEnabled' => false]);
        Offer::factory()->inactive()->create(['slug' => 'second', 'position' => 2]);
        Offer::factory()->create(['slug' => 'first', 'position' => 1]);

        $response = $this->getJson('/api/v1/admin/offers');

        $response->assertOk()
            ->assertJsonPath('isEnabled', false)
            ->assertJsonPath('eyebrow', 'عروض أهل الكرم')
            ->assertJsonPath('title', 'الكرم **بالعروض**')
            ->assertJsonPath('offers.1.isActive', false);
        expect(collect($response->json('offers'))->pluck('id')->all())->toBe(['first', 'second']);
    });
});

describe('store', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 201 with the new offer last in the order and a neutral slug', function () {
        Offer::factory()->create(['position' => 1]);
        $input = offerInput(['items' => ['فروج مشوي', '', 'ثوم'], 'imageStyle' => 'photo', 'badge' => 'جديد']);

        $response = $this->postJson('/api/v1/admin/offers', $input);

        $response->assertCreated()->assertJson([
            'title' => 'عرض الفروج',
            'items' => ['فروج مشوي', 'ثوم'],
            'freeItem' => 'لتر ببسي',
            'price' => 1250,
            'image' => $input['image'],
            'imageStyle' => 'photo',
            'badge' => 'جديد',
            'isActive' => true,
        ]);
        expect($response->json('id'))->toMatch('/^offer-[a-z0-9]{8}$/');
        $this->assertDatabaseHas('offers', ['slug' => $response->json('id'), 'position' => 2]);
    });

    it('keeps the slug the owner sent', function () {
        $response = $this->postJson('/api/v1/admin/offers', offerInput(['slug' => 'family-deal']));

        $response->assertCreated()->assertJsonPath('id', 'family-deal');
    });

    it('returns 422 for a slug a product or another offer already uses', function (string $taken) {
        Product::factory()->create(['slug' => 'chicken-shawarma']);
        Offer::factory()->create(['slug' => 'chicken-cola']);

        $response = $this->postJson('/api/v1/admin/offers', offerInput(['slug' => $taken]));

        $response->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'هالرابط مستعمل، اختار غيره']);
    })->with(['a product' => 'chicken-shawarma', 'an offer' => 'chicken-cola']);

    it('returns 422 with the frontend messages', function (array $overrides, string $field, string $message) {
        $response = $this->postJson('/api/v1/admin/offers', offerInput($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    })->with([
        'no title' => [['title' => '  '], 'title', 'اكتب اسم العرض'],
        'title too long' => [['title' => str_repeat('ع', 31)], 'title', 'الاسم لازم يكون 30 حرف أو أقل'],
        'no items' => [['items' => []], 'items', 'اكتب شو بياخد الزبون، متل: 4 سندويشات شاورما'],
        'only empty items' => [['items' => ['', ' ']], 'items', 'اكتب شو بياخد الزبون، متل: 4 سندويشات شاورما'],
        'too many items' => [['items' => array_fill(0, 7, 'شي')], 'items', 'أقصى شي 6 أشياء'],
        'an item too long' => [['items' => [str_repeat('ع', 41)]], 'items', 'كل شي لازم يكون 40 حرف أو أقل'],
        'no free item' => [['freeItem' => ''], 'freeItem', 'اكتب شو بياخد مجاناً'],
        'free item too long' => [['freeItem' => str_repeat('ع', 41)], 'freeItem', 'لازم يكون 40 حرف أو أقل'],
        'price not a number' => [['price' => 'كتير'], 'price', 'السعر لازم يكون رقم، متل 250'],
        'price zero' => [['price' => 0], 'price', 'السعر لازم يكون أكبر من صفر'],
        'price too big' => [['price' => 10_000_001], 'price', 'السعر كبير كتير، تأكد منه'],
        'no image' => [['image' => null], 'image', 'العرض بدّه صورة'],
        'badge too long' => [['badge' => str_repeat('ع', 21)], 'badge', 'الشارة لازم تكون 20 حرف أو أقل'],
    ]);

    it('returns 422 for a photo that is not one of our uploads', function () {
        $response = $this->postJson('/api/v1/admin/offers', offerInput(['image' => 'https://evil.example/x.webp']));

        $response->assertUnprocessable()->assertJsonValidationErrors(['image' => 'الصورة مش من الصور المرفوعة، ارفعها من جديد']);
    });
});

describe('update', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->offer = Offer::factory()->create(['slug' => 'chicken-cola', 'image_style' => 'photo', 'badge' => 'جديد', 'position' => 3]);
    });

    it('saves the whole offer and clears the optional fields it does not send', function () {
        $input = offerInput(['title' => 'عرض جديد', 'isActive' => false]);

        $response = $this->putJson('/api/v1/admin/offers/chicken-cola', $input);

        $response->assertOk()
            ->assertJsonPath('id', 'chicken-cola')
            ->assertJsonPath('title', 'عرض جديد')
            ->assertJsonPath('isActive', false)
            ->assertJsonMissingPath('imageStyle')
            ->assertJsonMissingPath('badge');
        $this->assertDatabaseHas('offers', ['id' => $this->offer->id, 'image_style' => null, 'badge' => null, 'position' => 3]);
    });

    it('changes the slug only when a new one is sent', function (?string $sent, string $expected) {
        $response = $this->putJson('/api/v1/admin/offers/chicken-cola', offerInput(['slug' => $sent]));

        $response->assertOk()->assertJsonPath('id', $expected);
    })->with([
        'none' => [null, 'chicken-cola'],
        'empty' => ['', 'chicken-cola'],
        'its own' => ['chicken-cola', 'chicken-cola'],
        'a new one' => ['chicken-deal', 'chicken-deal'],
    ]);

    it('returns 404 for an unknown offer', function () {
        $this->putJson('/api/v1/admin/offers/unknown', offerInput())->assertNotFound();
    });
});

describe('patch', function () {
    beforeEach(fn () => actingAsAdmin());

    it('switches the offer on or off and ignores the other fields', function () {
        $offer = Offer::factory()->create(['slug' => 'chicken-cola', 'title' => 'عرض الفروج']);

        $response = $this->patchJson('/api/v1/admin/offers/chicken-cola', ['isActive' => false, 'title' => 'غيره']);

        $response->assertOk()->assertJsonPath('isActive', false)->assertJsonPath('title', 'عرض الفروج');
        expect($offer->refresh()->is_active)->toBeFalse();
    });

    it('returns 422 when isActive is not a boolean', function () {
        Offer::factory()->create(['slug' => 'chicken-cola']);

        $response = $this->patchJson('/api/v1/admin/offers/chicken-cola', ['isActive' => 'maybe']);

        $response->assertUnprocessable()->assertJsonValidationErrors('isActive');
    });
});

describe('move', function () {
    beforeEach(function () {
        actingAsAdmin();
        foreach (['a', 'b', 'c'] as $index => $slug) {
            Offer::factory()->create(['slug' => $slug, 'position' => $index + 1]);
        }
    });

    it('swaps the offer with its neighbour and returns every offer in the new order', function (string $slug, string $direction, array $expected) {
        $response = $this->postJson("/api/v1/admin/offers/{$slug}/move", ['direction' => $direction]);

        $response->assertOk();
        expect(collect($response->json())->pluck('id')->all())->toBe($expected);
    })->with([
        'down' => ['a', 'down', ['b', 'a', 'c']],
        'up' => ['c', 'up', ['a', 'c', 'b']],
        'the first one up: unchanged' => ['a', 'up', ['a', 'b', 'c']],
    ]);
});

describe('destroy', function () {
    it('returns 204, deletes the offer and renumbers the rest with no gaps', function () {
        actingAsAdmin();
        foreach (['a', 'b', 'c'] as $index => $slug) {
            Offer::factory()->create(['slug' => $slug, 'position' => $index + 1]);
        }

        $response = $this->deleteJson('/api/v1/admin/offers/a');

        $response->assertNoContent();
        expect(Offer::orderBy('position')->pluck('position', 'slug')->all())->toBe(['b' => 1, 'c' => 2]);
    });
});

it('returns the photo URL from the stored path', function () {
    actingAsAdmin();
    Offer::factory()->create(['image_path' => 'uploads/a.webp']);

    $this->getJson('/api/v1/admin/offers')->assertJsonPath('offers.0.image', PublicImage::url('uploads/a.webp'));
});
