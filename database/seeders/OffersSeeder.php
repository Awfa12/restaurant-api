<?php

namespace Database\Seeders;

use App\Models\Offer;
use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\Support\SeedData;
use Database\Seeders\Support\SeedImages;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The offers section (settings) and the offers, from offers.json
 * (frontend src/data/mock/offers.ts). Called by DatabaseSeeder only.
 */
class OffersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SeedImages $images): void
    {
        $content = SeedData::read('offers');

        Setting::create(['key' => 'offers_section', 'value' => array_filter([
            'isEnabled' => $content['isEnabled'],
            'eyebrow' => $content['eyebrow'],
            'title' => $content['title'],
            'menuCardImagePath' => isset($content['menuCardImage']) ? $images->path($content['menuCardImage']) : null,
        ], fn ($value) => $value !== null)]);

        foreach ($content['offers'] as $index => $offer) {
            // Products and offers share /menu/<slug> (API-CONTRACT §2).
            if (Product::where('slug', $offer['id'])->exists()) {
                throw new RuntimeException("Offer {$offer['id']}: a product already uses that slug.");
            }

            Offer::create([
                'slug' => $offer['id'],
                'title' => $offer['title'],
                'items' => $offer['items'],
                'free_item' => $offer['freeItem'],
                'price' => $offer['price'],
                'image_path' => $images->path($offer['image']),
                'image_style' => $offer['imageStyle'] ?? null,
                'badge' => $offer['badge'] ?? null,
                'is_active' => $offer['isActive'],
                'position' => $index + 1,
            ]);
        }
    }
}
