<?php

use App\Models\Branch;
use App\Models\OrderingSetting;
use App\Models\Phone;
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
 * Stores the hero's texts, as the seeder does (docs/DATABASE.md §3).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeHero(array $overrides = []): array
{
    $value = array_merge([
        'badge' => 'من قلب السويداء',
        'headline' => 'شاورما بطعم أصيل',
        'primaryActionLabel' => 'اطلب الآن',
        'secondaryActionLabel' => 'من نحن',
        'features' => [
            ['label' => 'مكونات طازجة يوميًا', 'icon' => 'fresh'],
            ['label' => 'على الطريقة الشرقية الأصيلة', 'icon' => 'fire'],
            ['label' => 'جودة عالية وسر المذاق', 'icon' => 'quality'],
        ],
    ], $overrides);
    Setting::updateOrCreate(['key' => 'hero'], ['value' => $value]);

    return $value;
}

/**
 * Stores «من نحن», as the seeder does (the photo as a path).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function storeAbout(array $overrides = []): array
{
    $value = array_merge([
        'eyebrow' => 'من قلب الخبرة... إلى قلب المذاق',
        'title' => 'أهل الكرم... **اسم على مسمّى**',
        'paragraphs' => ['في أهل الكرم، الشاورما مو مجرد **وجبة**.', 'منختار مكوناتنا بعناية.'],
        'closing' => 'والكرم عنا مو مجرد اسم.',
        'imagePath' => 'uploads/about.webp',
        'imageAlt' => 'شيف أهل الكرم',
        'badge' => 'من قلب السويداء',
    ], $overrides);
    Setting::updateOrCreate(['key' => 'about'], ['value' => $value]);

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

/**
 * The restaurant as the seeder leaves it (docs/DATABASE.md §3 Restaurant):
 * two branches, two public numbers, one staff number per branch, delivery on
 * to the first public number, tables off. Returns the rows by name.
 *
 * @return array{qanawat: Branch, mehwari: Branch, main: Phone, second: Phone, qanawatStaff: Phone, mehwariStaff: Phone, ordering: OrderingSetting}
 */
function seedRestaurant(): array
{
    Setting::updateOrCreate(['key' => 'site_info'], ['value' => [
        'tagline' => "شاورما بطعم أصيل من قلب السويداء،\nبخبرة شيفنا وكرم أهل الجبل.",
        'openingHours' => 'يومياً من 9 الصبح لـ 12 بالليل',
        'openingHoursSchema' => ['Mo-Su 09:00-24:00'],
    ]]);

    $qanawat = Branch::factory()->create(['slug' => 'qanawat-road', 'name' => 'فرع طريق قنوات', 'address' => 'السويداء، طريق قنوات', 'position' => 1]);
    $mehwari = Branch::factory()->create(['slug' => 'al-mehwari', 'name' => 'فرع المحوري', 'address' => 'السويداء، المحوري', 'position' => 2]);

    $main = Phone::factory()->create(['number' => '+963930539614', 'position' => 1]);
    $second = Phone::factory()->create(['number' => '+963980785608', 'position' => 2]);
    $qanawatStaff = Phone::factory()->forBranch($qanawat)->create(['number' => '+963930000001', 'position' => 3]);
    $mehwariStaff = Phone::factory()->forBranch($mehwari)->create(['number' => '+963930000002', 'position' => 4]);

    $ordering = OrderingSetting::factory()->withDelivery($main)->create();

    return compact('qanawat', 'mehwari', 'main', 'second', 'qanawatStaff', 'mehwariStaff', 'ordering');
}
