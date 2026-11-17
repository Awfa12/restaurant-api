<?php

use App\Models\Setting;
use App\Models\Upload;
use App\Models\User;
use App\Support\PublicImage;
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

/**
 * The URL of a stored upload, as POST /admin/uploads returns it.
 */
function uploadedImageUrl(): string
{
    return PublicImage::url(Upload::factory()->create()->path);
}

/**
 * Stores the offers section's settings, as the seeder does (docs/DATABASE.md §3).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeOffersSection(array $overrides = []): array
{
    $value = array_merge(['isEnabled' => true, 'eyebrow' => 'عروض أهل الكرم', 'title' => 'الكرم **بالعروض**'], $overrides);
    Setting::updateOrCreate(['key' => 'offers_section'], ['value' => $value]);

    return $value;
}

/**
 * Stores the reviews section's settings, as the seeder does (docs/DATABASE.md §3).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeReviewsSection(array $overrides = []): array
{
    $value = array_merge([
        'isEnabled' => true,
        'eyebrow' => 'وصلتنا على واتساب',
        'title' => 'شو قالوا **عنّا**',
        'defaultQuestion' => 'كيف كانت تجربتك معنا اليوم؟',
    ], $overrides);
    Setting::updateOrCreate(['key' => 'reviews_section'], ['value' => $value]);

    return $value;
}
