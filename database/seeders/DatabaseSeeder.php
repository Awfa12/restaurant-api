<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedImages;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The restaurant's starting content: the frontend's mock data, exported to
 * database/seeders/data (docs/BACKEND_PLAN.md Phase 2). Slugs are kept;
 * sizes, phones, banners and reviews get database ids, and every reference
 * to them is remapped.
 *
 * It only runs on an EMPTY database, so it can never mix with or overwrite
 * the owner's edits (staging, production). Locally: `php artisan migrate:fresh --seed`.
 * No admin user here: `php artisan admin:create` (decision 2.1).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Any row in one of these means the database already has content.
     *
     * @var array<int, string>
     */
    public const CONTENT_TABLES = [
        'categories', 'products', 'product_sizes', 'offers', 'banners', 'reviews',
        'branches', 'phones', 'ordering_settings', 'settings',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->ensureDatabaseIsEmpty();

        $images = new SeedImages;
        $this->container->instance(SeedImages::class, $images);

        try {
            DB::transaction(fn () => $this->call([
                MenuSeeder::class,
                OffersSeeder::class,
                BannersSeeder::class,
                ReviewsSeeder::class,
                HomeContentSeeder::class,
                RestaurantSeeder::class,
            ]));
        } catch (Throwable $exception) {
            $images->deleteWritten();

            throw $exception;
        }
    }

    /**
     * Refuses before writing anything when any content table has rows.
     */
    protected function ensureDatabaseIsEmpty(): void
    {
        foreach (self::CONTENT_TABLES as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "Seeding refused: the database already has content (table `{$table}`). ".
                    'The seed data is only for an empty database, so it never mixes with or overwrites the owner\'s edits. '.
                    'Locally, start over with `php artisan migrate:fresh --seed`.'
                );
            }
        }
    }
}
