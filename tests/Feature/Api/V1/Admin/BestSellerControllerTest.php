<?php

use App\Models\Product;

/*
 * POST /admin/best-sellers/{id}/move (API-CONTRACT §4 Best sellers, §4.2).
 */

it('returns 401 without a token', function () {
    Product::factory()->bestSeller(1)->create(['slug' => 'first']);

    $response = $this->postJson('/api/v1/admin/best-sellers/first/move', ['direction' => 'down']);

    $response->assertUnauthorized();
});

it('moves a best seller and returns every best seller in slider order', function () {
    actingAsAdmin();
    Product::factory()->bestSeller(1)->create(['slug' => 'first']);
    Product::factory()->bestSeller(2)->create(['slug' => 'second']);
    Product::factory()->create(['slug' => 'not-a-best-seller']);

    $response = $this->postJson('/api/v1/admin/best-sellers/second/move', ['direction' => 'up']);

    $response->assertOk();
    expect(collect($response->json())->pluck('id')->all())->toBe(['second', 'first'])
        ->and(collect($response->json())->pluck('bestSellerOrder')->all())->toBe([1, 2]);
});

it('returns 409 with the dashboard\'s text for a product that is not a best seller', function () {
    actingAsAdmin();
    Product::factory()->create(['slug' => 'plain']);

    $response = $this->postJson('/api/v1/admin/best-sellers/plain/move', ['direction' => 'up']);

    $response->assertConflict()->assertExactJson(['message' => 'هالصنف مش بالأكثر مبيعاً. حدّث الصفحة.']);
});
