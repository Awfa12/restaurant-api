<?php

use App\Models\Setting;
use App\Support\PublicImage;

/*
 * PATCH /admin/offers-section (API-CONTRACT §4 Offers; frontend
 * updateOffersSettings, checkSectionText).
 */

beforeEach(fn () => actingAsAdmin());

it('changes only the fields that are sent and returns the section settings', function () {
    storeOffersSection(['menuCardImagePath' => 'uploads/card.webp']);

    $response = $this->patchJson('/api/v1/admin/offers-section', ['isEnabled' => false]);

    $response->assertOk()->assertExactJson([
        'isEnabled' => false,
        'eyebrow' => 'عروض أهل الكرم',
        'title' => 'الكرم **بالعروض**',
        'menuCardImage' => PublicImage::url('uploads/card.webp'),
    ]);
});

it('saves new texts', function () {
    storeOffersSection();

    $response = $this->patchJson('/api/v1/admin/offers-section', ['eyebrow' => 'عروضنا', 'title' => 'أطيب **العروض**']);

    $response->assertOk()->assertJsonPath('eyebrow', 'عروضنا')->assertJsonPath('title', 'أطيب **العروض**');
    expect(Setting::where('key', 'offers_section')->first()->value)
        ->toMatchArray(['eyebrow' => 'عروضنا', 'title' => 'أطيب **العروض**']);
});

it('sets the menu card photo from one of our uploads and stores its path', function () {
    storeOffersSection();
    $url = uploadedImageUrl();

    $response = $this->patchJson('/api/v1/admin/offers-section', ['menuCardImage' => $url]);

    $response->assertOk()->assertJsonPath('menuCardImage', $url);
    expect(Setting::where('key', 'offers_section')->first()->value['menuCardImagePath'])->toBe(PublicImage::pathFromUrl($url));
});

it('removes the menu card photo with menuCardImage: null', function () {
    storeOffersSection(['menuCardImagePath' => 'uploads/card.webp']);

    $response = $this->patchJson('/api/v1/admin/offers-section', ['menuCardImage' => null]);

    $response->assertOk()->assertJsonMissingPath('menuCardImage');
    expect(Setting::where('key', 'offers_section')->first()->value)->not->toHaveKey('menuCardImagePath');
});

it('returns 422 with the frontend messages', function (array $body, string $field, string $message) {
    storeOffersSection();

    $response = $this->patchJson('/api/v1/admin/offers-section', $body);

    $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
})->with([
    'empty eyebrow' => [['eyebrow' => ''], 'eyebrow', 'اكتب العنوان الصغير'],
    'eyebrow too long' => [['eyebrow' => str_repeat('ع', 31)], 'eyebrow', 'لازم يكون 30 حرف أو أقل'],
    'empty title' => [['title' => ''], 'title', 'اكتب العنوان'],
    'title too long without the markers' => [['title' => '**'.str_repeat('ع', 41).'**'], 'title', 'العنوان لازم يكون 40 حرف أو أقل'],
    'stray markers' => [['title' => 'الكرم **بالعروض'], 'title', 'في رمز ** زيادة، شيله'],
    'photo not one of ours' => [['menuCardImage' => 'https://evil.example/x.webp'], 'menuCardImage', 'الصورة مش من الصور المرفوعة، ارفعها من جديد'],
]);

it('counts the title without the markers, so 40 visible characters fit', function () {
    storeOffersSection();

    $response = $this->patchJson('/api/v1/admin/offers-section', ['title' => '**'.str_repeat('ع', 40).'**']);

    $response->assertOk();
});
