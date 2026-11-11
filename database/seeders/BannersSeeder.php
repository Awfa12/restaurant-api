<?php

namespace Database\Seeders;

use App\Enums\BannerTargetKind;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\Support\SeedData;
use Database\Seeders\Support\SeedImages;
use Illuminate\Database\Seeder;

/**
 * Menu banner slides, from banners.json (frontend src/data/mock/menu-banners.ts).
 * New database ids; a button's category or product target is remapped from
 * the slug to its key (an unknown slug leaves the target empty, as a
 * deleted target would). Called by DatabaseSeeder only, after MenuSeeder.
 */
class BannersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(SeedImages $images): void
    {
        foreach (SeedData::read('banners') as $index => $banner) {
            $target = $banner['action']['target'] ?? null;
            $kind = $target === null ? null : BannerTargetKind::from($target['kind']);

            Banner::create([
                'eyebrow' => $banner['eyebrow'] ?? null,
                'title' => $banner['title'],
                'image_path' => $images->path($banner['image']['src']),
                'image_alt' => $banner['image']['alt'],
                'action_label' => $banner['action']['label'] ?? null,
                'target_kind' => $kind,
                'target_category_id' => $kind === BannerTargetKind::Category ? Category::where('slug', $target['categoryId'])->value('id') : null,
                'target_product_id' => $kind === BannerTargetKind::Product ? Product::where('slug', $target['productId'])->value('id') : null,
                'is_active' => $banner['isActive'],
                'position' => $index + 1,
            ]);
        }
    }
}
