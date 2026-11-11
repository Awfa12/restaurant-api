<?php

namespace Database\Seeders\Support;

use RuntimeException;

/**
 * The frontend's mock data, exported to database/seeders/data/*.json by
 * export.mjs (`composer seed:export`). Same shapes as the frontend types.
 */
class SeedData
{
    /** Where the JSON files and their photos are. */
    public const DIRECTORY = __DIR__.'/../data';

    /**
     * One exported area: menu, offers, banners, reviews, hero, about, site-info, ordering.
     *
     * @return array<mixed>
     */
    public static function read(string $area): array
    {
        $file = self::DIRECTORY."/{$area}.json";

        if (! is_file($file)) {
            throw new RuntimeException("Seed data {$area}.json is missing: run `composer seed:export`.");
        }

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The committed copy of a frontend photo ("/assets/imgs/menu/x.webp" → data/images/imgs/menu/x.webp).
     */
    public static function imageFile(string $frontendPath): string
    {
        return self::DIRECTORY.'/images/'.preg_replace('#^/assets/#', '', $frontendPath);
    }
}
