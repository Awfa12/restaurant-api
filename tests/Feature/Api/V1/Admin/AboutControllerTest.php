<?php

use App\Models\Setting;
use App\Support\PublicImage;

/*
 * Dashboard «من نحن» (API-CONTRACT §4 About, decision 9.1; rules and texts
 * from the frontend's checkAboutInput).
 */

/**
 * A valid whole «من نحن», as the dashboard's form sends it (AboutContent).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function aboutInput(array $overrides = []): array
{
    return array_merge([
        'eyebrow' => 'من قلب الخبرة... إلى قلب المذاق',
        'title' => 'أهل الكرم... **اسم على مسمّى**',
        'paragraphs' => ['الشاورما **حكاية شغف** و**خبرة**.', 'منختار مكوناتنا بعناية.'],
        'closing' => 'والكرم عنا **طبع**.',
        'image' => ['src' => uploadedImageUrl(), 'alt' => 'شيف أهل الكرم'],
        'badge' => 'من قلب السويداء',
    ], $overrides);
}

it('returns 401 without a token', function (string $method) {
    $this->json($method, '/api/v1/admin/about', [])->assertUnauthorized();
})->with(['GET', 'PUT']);

it('returns the stored «من نحن» with the photo as a URL', function () {
    actingAsAdmin();
    storeAbout();

    $this->getJson('/api/v1/admin/about')->assertOk()
        ->assertJsonPath('image', ['src' => PublicImage::url('uploads/about.webp'), 'alt' => 'شيف أهل الكرم']);
});

describe('update', function () {
    beforeEach(function () {
        actingAsAdmin();
        storeAbout();
    });

    it('saves the whole section, the photo as a path, and returns it', function () {
        $input = aboutInput();

        $response = $this->putJson('/api/v1/admin/about', $input);

        $response->assertOk()->assertExactJson($input);
        expect(Setting::where('key', 'about')->first()->value)
            ->imagePath->toBe(PublicImage::pathFromUrl($input['image']['src']))
            ->imageAlt->toBe('شيف أهل الكرم');
    });

    it('uses the title without its markers as the alt text when none is sent', function () {
        $input = aboutInput();
        $input['image'] = ['src' => $input['image']['src']];

        $response = $this->putJson('/api/v1/admin/about', $input);

        $response->assertOk()->assertJsonPath('image.alt', 'أهل الكرم... اسم على مسمّى');
    });

    it('clears a badge it does not send', function () {
        $input = aboutInput();
        unset($input['badge']);

        $this->putJson('/api/v1/admin/about', $input)->assertOk()->assertJsonMissingPath('badge');
    });

    it('allows About its longer eyebrow (40) and paragraphs of 320 without the markers', function () {
        $input = aboutInput([
            'eyebrow' => str_repeat('ع', 40),
            'paragraphs' => ['**'.str_repeat('ع', 320).'**'],
        ]);

        $this->putJson('/api/v1/admin/about', $input)->assertOk();
    });

    it('refuses a whole save that leaves out the eyebrow or title (PUT, not PATCH)', function (string $field, string $message) {
        $input = aboutInput();
        unset($input[$field]);

        $response = $this->putJson('/api/v1/admin/about', $input);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
    })->with([
        'eyebrow' => ['eyebrow', 'اكتب العنوان الصغير'],
        'title' => ['title', 'اكتب العنوان'],
    ]);

    it('returns 422 with the frontend messages on the contract keys', function (Closure $overrides, string $field, string $message) {
        $response = $this->putJson('/api/v1/admin/about', aboutInput($overrides()));

        $response->assertUnprocessable();
        expect($response->json('errors')[$field] ?? [])->toContain($message);
    })->with([
        'eyebrow too long' => [fn () => ['eyebrow' => str_repeat('ع', 41)], 'eyebrow', 'لازم يكون 40 حرف أو أقل'],
        'title with stray markers' => [fn () => ['title' => 'أهل **الكرم'], 'title', 'في رمز ** زيادة، شيله'],
        'no paragraphs' => [fn () => ['paragraphs' => []], 'paragraphs', 'اكتب فقرة وحدة على الأقل'],
        'five paragraphs' => [fn () => ['paragraphs' => array_fill(0, 5, 'فقرة')], 'paragraphs', 'أقصى شي 4 فقرات'],
        'an empty paragraph, on its own index' => [fn () => ['paragraphs' => ['فقرة', '']], 'paragraphs.1', 'الفقرة فاضية، اكتبها أو احذفها'],
        'a paragraph too long' => [fn () => ['paragraphs' => [str_repeat('ع', 321)]], 'paragraphs.0', 'الفقرة 320 حرف أو أقل'],
        'a paragraph with stray markers' => [fn () => ['paragraphs' => ['فقرة', 'طعم **أصيل']], 'paragraphs.1', 'في رمز ** زيادة، شيله'],
        'no closing' => [fn () => ['closing' => ''], 'closing', 'اكتب الجملة الأخيرة'],
        'closing too long' => [fn () => ['closing' => str_repeat('ع', 121)], 'closing', 'لازم تكون 120 حرف أو أقل'],
        'no photo' => [fn () => ['image' => ['src' => '', 'alt' => 'x']], 'image', '«من نحن» بدّه صورة'],
        'photo not ours' => [fn () => ['image' => ['src' => 'https://evil.example/x.webp', 'alt' => 'x']], 'image', 'الصورة مش من الصور المرفوعة، ارفعها من جديد'],
        'badge too long' => [fn () => ['badge' => str_repeat('ع', 25)], 'badge', 'الشارة 24 حرف أو أقل'],
    ]);
});
