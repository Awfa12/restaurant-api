<?php

use App\Models\Review;

/*
 * GET /reviews (public, API-CONTRACT §3, decisions 6.2 and 6.3; frontend
 * services/content.ts getReviewsContent).
 */

it('returns the section with its visible reviews in order, without a token', function () {
    storeReviewsSection();
    $second = Review::factory()->create([
        'customer_name' => 'رنا', 'question' => 'شو رأيك بالفروج؟', 'answer' => 'الفروج **طيب كتير**', 'time' => '8:15 م', 'position' => 2,
    ]);
    $first = Review::factory()->create(['customer_name' => 'سامر', 'answer' => 'الشاورما **ما في متلها**', 'position' => 1]);
    Review::factory()->hidden()->create(['position' => 3]);

    $response = $this->getJson('/api/v1/reviews');

    $response->assertOk()->assertExactJson([
        'isEnabled' => true,
        'eyebrow' => 'وصلتنا على واتساب',
        'title' => 'شو قالوا **عنّا**',
        'defaultQuestion' => 'كيف كانت تجربتك معنا اليوم؟',
        'reviews' => [
            ['id' => (string) $first->id, 'customerName' => 'سامر', 'answer' => 'الشاورما **ما في متلها**', 'isVisible' => true],
            [
                'id' => (string) $second->id, 'customerName' => 'رنا', 'question' => 'شو رأيك بالفروج؟',
                'answer' => 'الفروج **طيب كتير**', 'time' => '8:15 م', 'isVisible' => true,
            ],
        ],
    ]);
});

it('returns null when the section is off or has no visible review', function (array $section, bool $hasVisibleReview) {
    storeReviewsSection($section);
    Review::factory()->hidden()->create();
    if ($hasVisibleReview) {
        Review::factory()->create();
    }

    $response = $this->getJson('/api/v1/reviews');

    $response->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe('null');
})->with([
    'section off, with a visible review' => [['isEnabled' => false], true],
    'section on, no visible review' => [[], false],
]);
