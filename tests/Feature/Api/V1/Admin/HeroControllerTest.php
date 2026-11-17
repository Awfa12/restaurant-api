<?php

use App\Models\Setting;

/*
 * Dashboard hero texts (API-CONTRACT §4 Hero; rules and texts from the
 * frontend's checkHeroInput).
 */

/**
 * A valid whole hero, as the dashboard's form sends it (HeroContent).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function heroInput(array $overrides = []): array
{
    return array_merge([
        'badge' => 'من قلب السويداء',
        'headline' => 'شاورما بطعم أصيل',
        'primaryActionLabel' => 'اطلب الآن',
        'secondaryActionLabel' => 'من نحن',
        'features' => [
            ['label' => 'مكونات طازجة يوميًا', 'icon' => 'fresh'],
            ['label' => 'على الطريقة الشرقية', 'icon' => 'fire'],
            ['label' => 'توصيل سريع', 'icon' => 'delivery'],
        ],
    ], $overrides);
}

/**
 * Three features, one of them changed.
 *
 * @param  array<string, mixed>  $feature
 * @return array<int, array<string, string>>
 */
function heroFeaturesWith(int $index, array $feature): array
{
    $features = heroInput()['features'];
    $features[$index] = array_merge($features[$index], $feature);

    return $features;
}

it('returns 401 without a token', function (string $method) {
    $this->json($method, '/api/v1/admin/hero', heroInput())->assertUnauthorized();
})->with(['GET', 'PUT']);

it('returns the stored hero', function () {
    actingAsAdmin();
    $hero = storeHero();

    $this->getJson('/api/v1/admin/hero')->assertOk()->assertExactJson($hero);
});

describe('update', function () {
    beforeEach(function () {
        actingAsAdmin();
        storeHero();
    });

    it('saves the whole hero and returns it', function () {
        $response = $this->putJson('/api/v1/admin/hero', heroInput());

        $response->assertOk()->assertExactJson(heroInput());
        // MySQL's json column re-sorts object keys: compare the data, not the order.
        expect(Setting::where('key', 'hero')->first()->value)->toEqual(heroInput());
    });

    it('clears a badge it does not send, and keeps only label and icon of each feature', function () {
        $input = heroInput(['features' => heroFeaturesWith(0, ['extra' => 'x'])]);
        unset($input['badge']);

        $response = $this->putJson('/api/v1/admin/hero', $input);

        $response->assertOk()->assertJsonMissingPath('badge')->assertJsonMissingPath('features.0.extra');
        expect(Setting::where('key', 'hero')->first()->value)->not->toHaveKey('badge');
    });

    it('returns 422 with the frontend messages on the contract keys', function (Closure $overrides, string $field, string $message) {
        $response = $this->putJson('/api/v1/admin/hero', heroInput($overrides()));

        $response->assertUnprocessable();
        expect($response->json('errors')[$field] ?? [])->toContain($message);
    })->with([
        'badge too long' => [fn () => ['badge' => str_repeat('ع', 25)], 'badge', 'الشارة 24 حرف أو أقل'],
        'no headline' => [fn () => ['headline' => ' '], 'headline', 'اكتب الجملة الرئيسية'],
        'headline too long' => [fn () => ['headline' => str_repeat('ع', 33)], 'headline', 'لازم تكون 32 حرف أو أقل'],
        'no first button' => [fn () => ['primaryActionLabel' => ''], 'primaryActionLabel', 'اكتب شو مكتوب على الزر'],
        'second button too long' => [fn () => ['secondaryActionLabel' => str_repeat('ع', 15)], 'secondaryActionLabel', 'نص الزر 14 حرف أو أقل'],
        'two features' => [fn () => ['features' => array_slice(heroInput()['features'], 0, 2)], 'features', 'الميزات لازم تكون تلاتة'],
        'four features' => [fn () => ['features' => [...heroInput()['features'], ['label' => 'كمان', 'icon' => 'heart']]], 'features', 'الميزات لازم تكون تلاتة'],
        'a feature without a label' => [fn () => ['features' => heroFeaturesWith(1, ['label' => ''])], 'features.1.label', 'اكتب الميزة'],
        'a feature label too long' => [fn () => ['features' => heroFeaturesWith(1, ['label' => str_repeat('ع', 29)])], 'features.1.label', '28 حرف أو أقل'],
        'an unknown icon' => [fn () => ['features' => heroFeaturesWith(2, ['icon' => 'pizza'])], 'features.2.icon', 'اختار أيقونة'],
    ]);
});
