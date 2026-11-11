<?php

namespace App\Support;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Every image path a record uses. uploads:prune keeps these and deletes the
 * rest. **Add a line here for every new image field** (docs/DATABASE.md §3 Uploads).
 */
class ImageReferences
{
    /**
     * Image fields inside `settings` JSON values, by key.
     *
     * @var array<string, string>
     */
    public const SETTINGS_IMAGE_FIELDS = [
        'about' => 'imagePath',
        'offers_section' => 'menuCardImagePath',
    ];

    /**
     * The paths in use, without duplicates.
     *
     * @return Collection<int, string>
     */
    public static function paths(): Collection
    {
        $columns = collect([
            Category::query()->whereNotNull('image_path')->pluck('image_path'),
            Product::query()->whereNotNull('image_path')->pluck('image_path'),
            Offer::query()->pluck('image_path'),
            Banner::query()->pluck('image_path'),
        ])->flatten();

        $settings = Setting::query()
            ->whereIn('key', array_keys(self::SETTINGS_IMAGE_FIELDS))
            ->get()
            ->map(fn (Setting $setting) => $setting->value[self::SETTINGS_IMAGE_FIELDS[$setting->key]] ?? null)
            ->filter();

        return $columns->merge($settings)->unique()->values();
    }
}
