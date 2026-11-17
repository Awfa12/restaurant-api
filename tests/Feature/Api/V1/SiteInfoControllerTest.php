<?php

/*
 * GET /site-info (public, API-CONTRACT §3; frontend services/content.ts
 * getSiteInfo). The branch staff phones are private: never in this response.
 */

it('returns the restaurant info without the branch staff phones, without a token', function () {
    $restaurant = seedRestaurant();

    $response = $this->getJson('/api/v1/site-info');

    $response->assertOk()->assertExactJson([
        'tagline' => "شاورما بطعم أصيل من قلب السويداء،\nبخبرة شيفنا وكرم أهل الجبل.",
        'phones' => [
            ['id' => (string) $restaurant['main']->id, 'number' => '+963930539614', 'hasWhatsApp' => true],
            ['id' => (string) $restaurant['second']->id, 'number' => '+963980785608', 'hasWhatsApp' => true],
        ],
        'branches' => [
            ['id' => 'qanawat-road', 'name' => 'فرع طريق قنوات', 'address' => 'السويداء، طريق قنوات'],
            ['id' => 'al-mehwari', 'name' => 'فرع المحوري', 'address' => 'السويداء، المحوري'],
        ],
        'openingHours' => 'يومياً من 9 الصبح لـ 12 بالليل',
        'openingHoursSchema' => ['Mo-Su 09:00-24:00'],
    ]);
    expect($response->getContent())->not->toContain('+963930000001')->not->toContain('+963930000002');
});

it('uses three queries whatever the number of phones and branches (no N+1)', function () {
    seedRestaurant();

    $this->expectsDatabaseQueryCount(3);

    $this->getJson('/api/v1/site-info')->assertOk();
});
