<?php

use App\Models\Category;
use App\Models\Product;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;

/*
 * The safety switches in AppServiceProvider (docs/GOOD_PRACTICES.md).
 */

afterEach(function () {
    // The switches are static: put back what the testing environment boots with.
    DB::prohibitDestructiveCommands(false);
    Model::shouldBeStrict();
    app()->detectEnvironment(fn () => 'testing');
});

it('throws on lazy loading outside production', function () {
    Product::factory()->count(2)->create();

    $categories = Category::all();

    expect(fn () => $categories->first()->products)->toThrow(LazyLoadingViolationException::class);
});

it('refuses migrate:fresh in production', function () {
    app()->detectEnvironment(fn () => 'production');
    (new AppServiceProvider(app()))->boot();

    $this->artisan('migrate:fresh')->assertFailed();
});

/**
 * Sends GET /menu $times times, as the frontend vouching for $browserIp.
 */
function fetchMenu(int $times, string $browserIp): void
{
    foreach (range(1, $times) as $request) {
        test()->withHeaders(['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => $browserIp])
            ->getJson('/api/v1/menu')
            ->assertOk();
    }
}

describe('throttle:public (decision 5.5)', function () {
    beforeEach(function () {
        config(['services.frontend.key' => 'shared-secret']);
    });

    it('returns 429 with the Arabic message and Retry-After after 300 requests in a minute', function () {
        fetchMenu(300, browserIp: '203.0.113.7');

        $response = $this->withHeaders(['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => '203.0.113.7'])
            ->getJson('/api/v1/menu');

        $response->assertTooManyRequests()
            ->assertExactJson(['message' => 'محاولات كتير، جرّب بعد دقيقة'])
            ->assertHeader('Retry-After');
    });

    it('counts each browser separately when the frontend vouches for its IP', function () {
        fetchMenu(300, browserIp: '203.0.113.7');

        $response = $this->withHeaders(['X-Frontend-Key' => 'shared-secret', 'X-Client-IP' => '198.51.100.20'])
            ->getJson('/api/v1/menu');

        $response->assertOk();
    });

    it('lets the client in again once the minute has passed', function () {
        fetchMenu(300, browserIp: '203.0.113.7');

        $this->travel(61)->seconds();

        fetchMenu(1, browserIp: '203.0.113.7');
    });
});
