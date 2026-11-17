<?php

use App\Models\Review;

/*
 * Dashboard reviews (API-CONTRACT §2 PUT/PATCH, §4 Reviews, decisions 6.1 and
 * 6.2; rules and texts from the frontend's checkReviewInput).
 */

/**
 * A valid whole review, as the dashboard's form sends it (ReviewInput).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reviewInput(array $overrides = []): array
{
    return array_merge([
        'customerName' => 'سامر',
        'question' => 'شو رأيك بالشاورما؟',
        'answer' => 'والله الشاورما عندكن **ما في متلها** 😍',
        'time' => '9:41 م',
        'isVisible' => true,
    ], $overrides);
}

it('returns 401 without a token', function (string $method, string $uri) {
    $review = Review::factory()->create();

    $response = $this->json($method, str_replace('{id}', (string) $review->id, $uri), []);

    $response->assertUnauthorized();
})->with([
    'list' => ['GET', '/api/v1/admin/reviews'],
    'create' => ['POST', '/api/v1/admin/reviews'],
    'save' => ['PUT', '/api/v1/admin/reviews/{id}'],
    'show or hide' => ['PATCH', '/api/v1/admin/reviews/{id}'],
    'delete' => ['DELETE', '/api/v1/admin/reviews/{id}'],
    'move' => ['POST', '/api/v1/admin/reviews/{id}/move'],
    'section' => ['PATCH', '/api/v1/admin/reviews-section'],
]);

describe('index', function () {
    it('returns the section settings flat with every review, hidden ones too, in order', function () {
        actingAsAdmin();
        storeReviewsSection(['isEnabled' => false]);
        $hidden = Review::factory()->hidden()->create(['position' => 2]);
        $shown = Review::factory()->create(['position' => 1]);

        $response = $this->getJson('/api/v1/admin/reviews');

        $response->assertOk()
            ->assertJsonPath('isEnabled', false)
            ->assertJsonPath('defaultQuestion', 'كيف كانت تجربتك معنا اليوم؟')
            ->assertJsonPath('reviews.1.isVisible', false);
        expect(collect($response->json('reviews'))->pluck('id')->all())->toBe([(string) $shown->id, (string) $hidden->id]);
    });
});

describe('store', function () {
    beforeEach(fn () => actingAsAdmin());

    it('returns 201 with the new review last in the order', function () {
        Review::factory()->create(['position' => 1]);

        $response = $this->postJson('/api/v1/admin/reviews', reviewInput());

        $response->assertCreated()->assertJson(reviewInput());
        $this->assertDatabaseHas('reviews', ['id' => (int) $response->json('id'), 'position' => 2]);
    });

    it('leaves out an empty question and time (the site shows the default question)', function () {
        $response = $this->postJson('/api/v1/admin/reviews', reviewInput(['question' => '', 'time' => null]));

        $response->assertCreated()->assertJsonMissingPath('question')->assertJsonMissingPath('time');
        $this->assertDatabaseHas('reviews', ['id' => (int) $response->json('id'), 'question' => null, 'time' => null]);
    });

    it('counts the answer without the ** markers, so 220 visible characters fit', function () {
        $answer = '**'.str_repeat('ع', 100).'** '.str_repeat('ب', 119);

        $this->postJson('/api/v1/admin/reviews', reviewInput(['answer' => $answer]))->assertCreated();
    });

    it('returns 422 with the frontend messages', function (array $overrides, string $field, string $message) {
        $response = $this->postJson('/api/v1/admin/reviews', reviewInput($overrides));

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    })->with([
        'no name' => [['customerName' => ' '], 'customerName', 'اكتب اسم الزبون'],
        'name too long' => [['customerName' => str_repeat('ع', 21)], 'customerName', 'الاسم 20 حرف أو أقل'],
        'question too long' => [['question' => str_repeat('ع', 121)], 'question', 'السؤال 120 حرف أو أقل'],
        'no answer' => [['answer' => ''], 'answer', 'اكتب شو قال الزبون'],
        'answer of markers only' => [['answer' => '** **'], 'answer', 'اكتب شو قال الزبون'],
        'answer too long' => [['answer' => str_repeat('ع', 221)], 'answer', 'الرد 220 حرف أو أقل، لحتى يبيّن كامل بالكرت'],
        'stray markers' => [['answer' => 'طيب **كتير'], 'answer', 'في رمز ** زيادة، شيله'],
        'time too long' => [['time' => str_repeat('9', 13)], 'time', 'الوقت مش مفهوم، اختاره من الساعة'],
    ]);
});

describe('update', function () {
    beforeEach(fn () => actingAsAdmin());

    it('saves the whole review and clears the question and time it does not send', function () {
        $review = Review::factory()->create(['question' => 'قديم', 'time' => '1:00 م', 'position' => 3]);
        $input = reviewInput(['customerName' => 'رنا', 'isVisible' => false]);
        unset($input['question'], $input['time']);

        $response = $this->putJson("/api/v1/admin/reviews/{$review->id}", $input);

        $response->assertOk()
            ->assertJsonPath('customerName', 'رنا')
            ->assertJsonPath('isVisible', false)
            ->assertJsonMissingPath('question')
            ->assertJsonMissingPath('time');
        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'question' => null, 'time' => null, 'position' => 3]);
    });

    it('returns 404 for an unknown review', function () {
        $this->putJson('/api/v1/admin/reviews/999', reviewInput())->assertNotFound();
    });
});

describe('patch', function () {
    it('shows or hides the review and ignores the other fields', function () {
        actingAsAdmin();
        $review = Review::factory()->create(['customer_name' => 'سامر']);

        $response = $this->patchJson("/api/v1/admin/reviews/{$review->id}", ['isVisible' => false, 'customerName' => 'غيره']);

        $response->assertOk()->assertJsonPath('isVisible', false)->assertJsonPath('customerName', 'سامر');
        expect($review->refresh()->is_visible)->toBeFalse();
    });
});

describe('move and destroy', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->reviews = collect([1, 2, 3])->map(fn (int $position) => Review::factory()->create(['position' => $position]));
    });

    it('swaps the review with its neighbour and returns every review in the new order', function () {
        [$a, $b, $c] = $this->reviews->all();

        $response = $this->postJson("/api/v1/admin/reviews/{$c->id}/move", ['direction' => 'up']);

        $response->assertOk();
        expect(collect($response->json())->pluck('id')->all())->toBe([(string) $a->id, (string) $c->id, (string) $b->id]);
    });

    it('returns 204, deletes the review and renumbers the rest with no gaps', function () {
        [$a, $b, $c] = $this->reviews->all();

        $this->deleteJson("/api/v1/admin/reviews/{$b->id}")->assertNoContent();

        expect(Review::orderBy('position')->pluck('position', 'id')->all())->toBe([$a->id => 1, $c->id => 2]);
    });
});
