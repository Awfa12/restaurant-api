<?php

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

/*
 * Phase 0: the API-wide behaviour every endpoint relies on. The test routes
 * use plain get()/post() on purpose: they send no `Accept: application/json`,
 * so they prove the API answers in JSON even when a caller forgets the header.
 */

beforeEach(function () {
    Route::middleware('api')->prefix('api/v1')->group(function () {
        Route::post('_test/validation', fn (Request $request) => $request->validate([
            'name' => ['required', 'string'],
            'price' => ['required', 'integer'],
        ]));

        Route::get('_test/protected', fn () => response()->noContent())
            ->middleware('auth:sanctum');

        Route::get('_test/resource', fn () => JsonResource::make(['id' => '1', 'name' => 'شاورما']));
    });
});

it('returns 422 JSON with Arabic messages when validation fails without an Accept header', function () {
    $response = $this->post('/api/v1/_test/validation', ['price' => 'abc']);

    $response->assertUnprocessable()
        ->assertExactJson([
            'message' => 'هالحقل مطلوب (وفي غلط تاني)',
            'errors' => [
                'name' => ['هالحقل مطلوب'],
                'price' => ['لازم يكون رقم صحيح'],
            ],
        ]);
});

it('returns 401 JSON instead of a login redirect when no token is sent', function () {
    $response = $this->get('/api/v1/_test/protected');

    $response->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns 401 JSON when the token is not a valid Sanctum token', function () {
    $response = $this->withToken('not-a-real-token')->get('/api/v1/_test/protected');

    $response->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.']);
});

it('returns resources without a data wrapper', function () {
    $response = $this->get('/api/v1/_test/resource');

    $response->assertOk()
        ->assertExactJson(['id' => '1', 'name' => 'شاورما']);
});

it('returns 404 JSON for an unknown API route', function () {
    $response = $this->get('/api/v1/does-not-exist');

    $response->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('returns 404 at the site root because the API serves no web pages', function () {
    $response = $this->get('/');

    $response->assertNotFound();
});

it('returns 200 from the health check', function () {
    $response = $this->get('/up');

    $response->assertOk();
});
