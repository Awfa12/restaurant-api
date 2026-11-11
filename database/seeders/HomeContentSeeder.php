<?php

namespace Database\Seeders;

use App\Models\Setting;
use Database\Seeders\Support\SeedData;
use Database\Seeders\Support\SeedImages;
use Illuminate\Database\Seeder;

/**
 * The hero and «من نحن» texts (settings), from hero.json and about.json
 * (frontend src/data/mock/hero.ts, about.ts). Called by DatabaseSeeder only.
 */
class HomeContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SeedImages $images): void
    {
        $hero = SeedData::read('hero');

        Setting::create(['key' => 'hero', 'value' => array_filter([
            'badge' => $hero['badge'] ?? null,
            'headline' => $hero['headline'],
            'primaryActionLabel' => $hero['primaryActionLabel'],
            'secondaryActionLabel' => $hero['secondaryActionLabel'],
            'features' => $hero['features'],
        ], fn ($value) => $value !== null)]);

        $about = SeedData::read('about');

        // Images are stored as a path (docs/DATABASE.md §3 settings).
        Setting::create(['key' => 'about', 'value' => array_filter([
            'eyebrow' => $about['eyebrow'],
            'title' => $about['title'],
            'paragraphs' => $about['paragraphs'],
            'closing' => $about['closing'],
            'imagePath' => $images->path($about['image']['src']),
            'imageAlt' => $about['image']['alt'],
            'badge' => $about['badge'] ?? null,
        ], fn ($value) => $value !== null)]);
    }
}
