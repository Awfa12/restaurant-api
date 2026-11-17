<?php

use App\Support\PublicImage;

/*
 * GET /hero and GET /about (public, API-CONTRACT §3; frontend services/content.ts
 * getHeroContent / getAboutContent). Nothing is hidden: the same as the admin GETs.
 */

it('returns the hero texts in the frontend shape, without a token', function () {
    $hero = storeHero();

    $response = $this->getJson('/api/v1/hero');

    $response->assertOk()->assertExactJson($hero);
});

it('returns «من نحن» with the photo as a URL, without a token', function () {
    storeAbout();

    $response = $this->getJson('/api/v1/about');

    $response->assertOk()->assertExactJson([
        'eyebrow' => 'من قلب الخبرة... إلى قلب المذاق',
        'title' => 'أهل الكرم... **اسم على مسمّى**',
        'paragraphs' => ['في أهل الكرم، الشاورما مو مجرد **وجبة**.', 'منختار مكوناتنا بعناية.'],
        'closing' => 'والكرم عنا مو مجرد اسم.',
        'image' => ['src' => PublicImage::url('uploads/about.webp'), 'alt' => 'شيف أهل الكرم'],
        'badge' => 'من قلب السويداء',
    ]);
});

it('leaves out a badge the record does not have', function (string $uri, Closure $store) {
    $store();

    $this->getJson($uri)->assertOk()->assertJsonMissingPath('badge');
})->with([
    'hero' => ['/api/v1/hero', fn () => storeHero(['badge' => null])],
    'about' => ['/api/v1/about', fn () => storeAbout(['badge' => null])],
]);
