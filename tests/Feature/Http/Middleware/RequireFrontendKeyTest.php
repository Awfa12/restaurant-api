<?php

use App\Http\Middleware\RequireFrontendKey;

/*
 * API_REQUIRE_FRONTEND_KEY (decision 5.6): with it on, only callers with the
 * shared X-Frontend-Key reach /api/v1. On for the demo server, off by default.
 */

it('lets requests in without the key when the gate is off', function () {
    config(['services.frontend.key' => 'shared-secret']);

    $response = $this->getJson('/api/v1/menu');

    $response->assertOk();
});

describe('with the gate on', function () {
    beforeEach(function () {
        config(['services.frontend.required' => true]);
    });

    it('lets the frontend in with the right key', function () {
        config(['services.frontend.key' => 'shared-secret']);

        $response = $this->withHeader('X-Frontend-Key', 'shared-secret')->getJson('/api/v1/menu');

        $response->assertOk();
    });

    it('returns 403 with an Arabic message without the right key', function (?string $configuredKey, array $headers) {
        config(['services.frontend.key' => $configuredKey]);

        $response = $this->withHeaders($headers)->getJson('/api/v1/menu');

        $response->assertForbidden()
            ->assertExactJson(['message' => RequireFrontendKey::REFUSED]);
    })->with([
        'no key sent' => ['shared-secret', []],
        'wrong key' => ['shared-secret', ['X-Frontend-Key' => 'guessed']],
        'no key configured on the server' => [null, ['X-Frontend-Key' => '']],
    ]);

    it('returns 403 on a dashboard route even with a valid admin token', function () {
        config(['services.frontend.key' => 'shared-secret']);
        actingAsAdmin();

        $response = $this->getJson('/api/v1/admin/categories');

        $response->assertForbidden();
    });

    it('keeps the /up health check open without the key', function () {
        config(['services.frontend.key' => 'shared-secret']);

        $response = $this->get('/up');

        $response->assertOk();
    });
});
