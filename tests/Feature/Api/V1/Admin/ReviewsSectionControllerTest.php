<?php

use App\Models\Setting;

/*
 * PATCH /admin/reviews-section (API-CONTRACT §4 Reviews; frontend
 * updateReviewsSettings, checkReviewsSettings).
 */

beforeEach(fn () => actingAsAdmin());

it('changes only the fields that are sent and returns the section settings', function () {
    storeReviewsSection();

    $response = $this->patchJson('/api/v1/admin/reviews-section', ['defaultQuestion' => 'شو رأيك فينا؟']);

    $response->assertOk()->assertExactJson([
        'isEnabled' => true,
        'eyebrow' => 'وصلتنا على واتساب',
        'title' => 'شو قالوا **عنّا**',
        'defaultQuestion' => 'شو رأيك فينا؟',
    ]);
    expect(Setting::where('key', 'reviews_section')->first()->value['defaultQuestion'])->toBe('شو رأيك فينا؟');
});

it('switches the section off', function () {
    storeReviewsSection();

    $this->patchJson('/api/v1/admin/reviews-section', ['isEnabled' => false])->assertOk()->assertJsonPath('isEnabled', false);
});

it('returns 422 with the frontend messages', function (array $body, string $field, string $message) {
    storeReviewsSection();

    $response = $this->patchJson('/api/v1/admin/reviews-section', $body);

    $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
})->with([
    'empty eyebrow' => [['eyebrow' => ''], 'eyebrow', 'اكتب العنوان الصغير'],
    'title too long without the markers' => [['title' => '**'.str_repeat('ع', 41).'**'], 'title', 'العنوان لازم يكون 40 حرف أو أقل'],
    'stray markers' => [['title' => 'شو قالوا **عنّا'], 'title', 'في رمز ** زيادة، شيله'],
    'empty default question' => [['defaultQuestion' => ''], 'defaultQuestion', 'اكتب السؤال اللي بيطلع فوق كل رأي'],
    'default question too long' => [['defaultQuestion' => str_repeat('ع', 121)], 'defaultQuestion', 'السؤال 120 حرف أو أقل'],
]);
