<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The shared secret between the Next.js server and this API (X-Frontend-Key,
 * FRONTEND_API_KEY, sent privately). Proves a request came from the frontend's
 * server, which is what lets us trust its X-Client-IP and, when
 * API_REQUIRE_FRONTEND_KEY is on, let the request in at all.
 */
class FrontendKey
{
    /**
     * Whether the request carries the right key. Never true when no key is
     * configured, so an empty FRONTEND_API_KEY can't be matched by an empty header.
     */
    public static function isValid(Request $request): bool
    {
        $expectedKey = config('services.frontend.key');
        $sentKey = $request->header('X-Frontend-Key');

        return is_string($expectedKey) && $expectedKey !== ''
            && is_string($sentKey) && hash_equals($expectedKey, $sentKey);
    }

    /**
     * The browser's IP when the Next.js server vouches for it (X-Client-IP with
     * the right key), otherwise the IP the request came from (Vercel's, for
     * traffic through the frontend). A direct caller can't pick its own IP.
     */
    public static function clientIp(Request $request): string
    {
        $forwardedIp = $request->header('X-Client-IP');

        $isTrusted = self::isValid($request)
            && filter_var($forwardedIp, FILTER_VALIDATE_IP) !== false;

        return $isTrusted ? $forwardedIp : (string) $request->ip();
    }
}
