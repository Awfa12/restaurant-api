<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL test database, refreshed for each test.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Signs in as the dashboard admin with a Sanctum token. Laravel's actingAs()
 * uses the session guard, which Sanctum doesn't check here (sanctum.guard = []).
 */
function actingAsAdmin(?User $admin = null): User
{
    $admin ??= User::factory()->create();
    Sanctum::actingAs($admin);

    return $admin;
}
