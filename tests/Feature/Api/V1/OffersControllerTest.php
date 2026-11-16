<?php

use App\Models\Offer;
use App\Support\PublicImage;

/*
 * GET /offers (public, API-CONTRACT §3; frontend services/content.ts getOffersContent).
 */

it('returns the section with its active offers in order, without a token', function () {
    storeOffersSection(['menuCardImagePath' => 'uploads/card.webp']);
    Offer::factory()->create([
        'slug' => 'shawarma-4-plus-1', 'title' => 'عرض 4+1', 'items' => ['4 سندويشات شاورما جاج'],
        'free_item' => 'سندويشة شاورما خامسة', 'price' => 1000, 'image_path' => 'uploads/b.webp',
        'image_style' => 'photo', 'badge' => 'الأكثر طلباً', 'position' => 2,
    ]);
    Offer::factory()->create([
        'slug' => 'chicken-cola', 'title' => 'عرض الفروج', 'items' => ['فروج مشوي', 'بطاطا'],
        'free_item' => 'لتر ببسي', 'price' => 1250, 'image_path' => 'uploads/a.webp', 'position' => 1,
    ]);

    $response = $this->getJson('/api/v1/offers');

    $response->assertOk()->assertExactJson([
        'isEnabled' => true,
        'eyebrow' => 'عروض أهل الكرم',
        'title' => 'الكرم **بالعروض**',
        'menuCardImage' => PublicImage::url('uploads/card.webp'),
        'offers' => [
            [
                'id' => 'chicken-cola', 'title' => 'عرض الفروج', 'items' => ['فروج مشوي', 'بطاطا'],
                'freeItem' => 'لتر ببسي', 'price' => 1250, 'image' => PublicImage::url('uploads/a.webp'), 'isActive' => true,
            ],
            [
                'id' => 'shawarma-4-plus-1', 'title' => 'عرض 4+1', 'items' => ['4 سندويشات شاورما جاج'],
                'freeItem' => 'سندويشة شاورما خامسة', 'price' => 1000, 'image' => PublicImage::url('uploads/b.webp'),
                'imageStyle' => 'photo', 'badge' => 'الأكثر طلباً', 'isActive' => true,
            ],
        ],
    ]);
});

it('leaves out inactive offers and a menu card photo the section does not have', function () {
    storeOffersSection();
    Offer::factory()->create(['slug' => 'on']);
    Offer::factory()->inactive()->create(['slug' => 'off']);

    $response = $this->getJson('/api/v1/offers');

    $response->assertOk()->assertJsonMissingPath('menuCardImage');
    expect(collect($response->json('offers'))->pluck('id')->all())->toBe(['on']);
});

it('returns null when the section is off or has no active offer', function (array $section, bool $hasActiveOffer) {
    storeOffersSection($section);
    Offer::factory()->inactive()->create();
    if ($hasActiveOffer) {
        Offer::factory()->create();
    }

    $response = $this->getJson('/api/v1/offers');

    $response->assertOk()->assertHeader('Content-Type', 'application/json');
    expect($response->getContent())->toBe('null');
})->with([
    'section off, with an active offer' => [['isEnabled' => false], true],
    'section on, no active offer' => [[], false],
]);
