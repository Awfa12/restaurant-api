<?php

use App\Models\Phone;

/*
 * GET /ordering (public, API-CONTRACT §3, decision 11.1; frontend
 * services/ordering.ts getOrderingOptions). Only what's on and can reach a
 * WhatsApp number; the numbers are worked out here (staff phones are private
 * in the public site info).
 */

beforeEach(function () {
    $this->restaurant = seedRestaurant();
    $this->restaurant['ordering']->update(['dine_in_enabled' => true]);
    $this->restaurant['qanawat']->update(['dine_in_enabled' => true, 'table_count' => 10]);
    $this->restaurant['mehwari']->update(['dine_in_enabled' => true, 'table_count' => 6]);
});

it('returns delivery and the branches taking table orders with their WhatsApp numbers, without a token', function () {
    $response = $this->getJson('/api/v1/ordering');

    $response->assertOk()->assertExactJson([
        'delivery' => ['fee' => 150, 'whatsApp' => '+963930539614'],
        'dineInBranches' => [
            ['id' => 'qanawat-road', 'name' => 'فرع طريق قنوات', 'tableCount' => 10, 'whatsApp' => '+963930000001'],
            ['id' => 'al-mehwari', 'name' => 'فرع المحوري', 'tableCount' => 6, 'whatsApp' => '+963930000002'],
        ],
        'message' => ['greeting' => 'مرحبا {restaurant}، بدي اطلب:', 'closing' => 'شكراً إلكن'],
        'thankYou' => 'شكراً {name}!',
    ]);
});

it('sends delivery as null when it is off or its phone has no WhatsApp', function (Closure $change) {
    $change($this->restaurant);

    $response = $this->getJson('/api/v1/ordering');

    $response->assertOk()->assertJsonPath('delivery', null);
    expect($response->json())->toHaveKey('delivery');
})->with([
    'off' => fn (array $restaurant) => $restaurant['ordering']->update(['delivery_enabled' => false]),
    'no WhatsApp' => fn (array $restaurant) => $restaurant['main']->update(['has_whatsapp' => false]),
]);

it('leaves out every branch while table orders are off', function () {
    $this->restaurant['ordering']->update(['dine_in_enabled' => false]);

    $this->getJson('/api/v1/ordering')->assertOk()->assertJsonPath('dineInBranches', []);
});

it('leaves out a branch that is off, has no tables, or has no WhatsApp phone of its own', function (Closure $change) {
    $change($this->restaurant);

    $response = $this->getJson('/api/v1/ordering');

    expect(collect($response->json('dineInBranches'))->pluck('id')->all())->toBe(['qanawat-road']);
})->with([
    'off' => fn (array $restaurant) => $restaurant['mehwari']->update(['dine_in_enabled' => false]),
    'no tables' => fn (array $restaurant) => $restaurant['mehwari']->update(['table_count' => 0]),
    'no WhatsApp phone' => fn (array $restaurant) => $restaurant['mehwariStaff']->update(['has_whatsapp' => false]),
]);

it('sends the first WhatsApp phone of the branch, in list order', function () {
    $this->restaurant['qanawatStaff']->update(['has_whatsapp' => false, 'position' => 3]);
    Phone::factory()->forBranch($this->restaurant['qanawat'])->create(['number' => '+963944444444', 'position' => 6]);
    Phone::factory()->forBranch($this->restaurant['qanawat'])->create(['number' => '+963955555555', 'position' => 5]);

    $response = $this->getJson('/api/v1/ordering');

    $response->assertJsonPath('dineInBranches.0.whatsApp', '+963955555555');
});

it('uses four queries whatever the number of branches and phones (no N+1)', function () {
    $this->expectsDatabaseQueryCount(4);

    $this->getJson('/api/v1/ordering')->assertOk();
});
