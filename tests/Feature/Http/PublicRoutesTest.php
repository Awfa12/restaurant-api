<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

/*
 * Every public /api/v1 route is rate limited (decision 5.5; BACKEND_PLAN
 * cross-cutting rules). A route added outside the throttle:public group would
 * be the one unlimited door on a shared host. The limiter itself is tested in
 * AppServiceProviderTest.
 */

it('puts every route without a token in the throttle:public group', function () {
    $public = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/v1/'))
        ->reject(fn (RoutingRoute $route) => in_array('auth:sanctum', $route->gatherMiddleware(), true))
        // Login has its own limit on failed tries (LoginRequest, decision 3.2).
        ->reject(fn (RoutingRoute $route) => $route->getName() === 'auth.login');

    $unlimited = $public
        ->reject(fn (RoutingRoute $route) => in_array('throttle:public', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route) => $route->uri())
        ->values()
        ->all();

    expect($public)->not->toBeEmpty()
        ->and($unlimited)->toBe([]);
});
