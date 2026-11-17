<?php

use App\Enums\SocialPlatform;
use App\Support\SocialLinks;

/*
 * Social profile links, as the frontend's normalizeSocialUrl (lib/site-info).
 */

it('normalizes a typed profile link', function (SocialPlatform $platform, string $typed, string $expected) {
    expect(SocialLinks::normalize($platform, $typed))->toBe($expected);
})->with([
    'no scheme' => [SocialPlatform::Instagram, 'instagram.com/ahlalkaram', 'https://instagram.com/ahlalkaram'],
    'www and a trailing slash' => [SocialPlatform::Instagram, 'https://www.instagram.com/ahlalkaram/', 'https://instagram.com/ahlalkaram'],
    'mobile host, upper case' => [SocialPlatform::Facebook, 'HTTP://m.Facebook.com/ahlalkaram', 'https://facebook.com/ahlalkaram'],
    'another host of the platform' => [SocialPlatform::Facebook, 'fb.me/ahlalkaram', 'https://fb.me/ahlalkaram'],
    'query kept' => [SocialPlatform::Facebook, 'facebook.com/profile.php?id=123', 'https://facebook.com/profile.php?id=123'],
    'threads handle' => [SocialPlatform::Threads, 'threads.net/@ahlalkaram', 'https://threads.net/@ahlalkaram'],
]);

it('refuses a link that is not a page on the platform', function (SocialPlatform $platform, string $typed) {
    expect(SocialLinks::normalize($platform, $typed))->toBeNull();
})->with([
    'empty' => [SocialPlatform::Instagram, '  '],
    'another platform' => [SocialPlatform::Instagram, 'facebook.com/ahlalkaram'],
    'a look-alike host' => [SocialPlatform::Instagram, 'instagram.com.evil.example/x'],
    'no page, only the site' => [SocialPlatform::Tiktok, 'https://tiktok.com/'],
    'spaces inside' => [SocialPlatform::Instagram, 'instagram.com/ahl alkaram'],
]);

it('accepts only links already in their normalized form', function () {
    expect(SocialLinks::isNormalized(SocialPlatform::Instagram, 'https://instagram.com/ahlalkaram'))->toBeTrue()
        ->and(SocialLinks::isNormalized(SocialPlatform::Instagram, 'instagram.com/ahlalkaram'))->toBeFalse()
        ->and(SocialLinks::isNormalized(SocialPlatform::Instagram, 'https://instagram.com/ahlalkaram#top'))->toBeFalse();
});
