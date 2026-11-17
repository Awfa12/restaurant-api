<?php

namespace App\Support;

use App\Enums\SocialPlatform;

/**
 * Social profile links, ported from the frontend's normalizeSocialUrl
 * (lib/site-info): the dashboard saves the normalized form, so the API only
 * accepts a link that is already in it.
 */
class SocialLinks
{
    /**
     * "instagram.com/x" or "www.instagram.com/x/" → "https://instagram.com/x";
     * null when it isn't a page on that platform (another host, or no path).
     */
    public static function normalize(SocialPlatform $platform, string $text): ?string
    {
        $typed = trim($text);

        if ($typed === '' || preg_match('/\s/', $typed)) {
            return null;
        }

        $parts = parse_url(preg_match('/^https?:\/\//i', $typed) ? $typed : "https://{$typed}");

        if ($parts === false || ! isset($parts['host'])) {
            return null;
        }

        $host = preg_replace('/^(www|m)\./', '', strtolower($parts['host']));
        $path = rtrim($parts['path'] ?? '', '/');

        if (! in_array($host, $platform->hosts(), true) || $path === '') {
            return null;
        }

        return "https://{$host}{$path}".(isset($parts['query']) ? "?{$parts['query']}" : '');
    }

    /**
     * Whether the link is a page on the platform, already in its normalized form.
     */
    public static function isNormalized(SocialPlatform $platform, string $url): bool
    {
        return self::normalize($platform, $url) === $url;
    }
}
