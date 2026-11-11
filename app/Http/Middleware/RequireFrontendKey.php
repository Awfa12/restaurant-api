<?php

namespace App\Http\Middleware;

use App\Support\FrontendKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * With API_REQUIRE_FRONTEND_KEY=true, every /api/v1 request needs the right
 * X-Frontend-Key: only the Next.js server (and our Postman) get in. Keeps the
 * demo server private. Basic Auth can't do it: it uses the same Authorization
 * header as the Bearer tokens. /up is outside the api group, so it stays open.
 *
 * Fails closed: on with no FRONTEND_API_KEY set refuses everything
 * (bin/deploy.sh refuses that combination before it gets here).
 */
class RequireFrontendKey
{
    /** Arabic, shown by the frontend as is (it only happens if her server is misconfigured). */
    public const REFUSED = 'ما عندك صلاحية';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('services.frontend.required') && ! FrontendKey::isValid($request)) {
            return response()->json(['message' => self::REFUSED], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
