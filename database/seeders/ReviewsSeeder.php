<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\Setting;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;

/**
 * The reviews section (settings) and the reviews, from reviews.json
 * (frontend src/data/mock/reviews.ts). New database ids. Called by DatabaseSeeder only.
 */
class ReviewsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $content = SeedData::read('reviews');

        Setting::create(['key' => 'reviews_section', 'value' => [
            'isEnabled' => $content['isEnabled'],
            'eyebrow' => $content['eyebrow'],
            'title' => $content['title'],
            'defaultQuestion' => $content['defaultQuestion'],
        ]]);

        foreach ($content['reviews'] as $index => $review) {
            Review::create([
                'customer_name' => $review['customerName'],
                'question' => $review['question'] ?? null,
                'answer' => $review['answer'],
                'time' => $review['time'] ?? null,
                'is_visible' => $review['isVisible'],
                'position' => $index + 1,
            ]);
        }
    }
}
