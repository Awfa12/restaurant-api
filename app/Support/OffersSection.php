<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Setting;

/**
 * The offers section's settings: one `settings` row, key `offers_section`
 * (docs/DATABASE.md §3), stored as { isEnabled, eyebrow, title,
 * menuCardImagePath? }. The seeder creates it on every server.
 */
class OffersSection
{
    public const KEY = 'offers_section';

    /**
     * The stored settings; a section that was never seeded counts as off.
     *
     * @return array{isEnabled: bool, eyebrow: string, title: string, menuCardImagePath?: string}
     */
    public static function get(): array
    {
        return Setting::where('key', self::KEY)->first()?->value
            ?? ['isEnabled' => false, 'eyebrow' => '', 'title' => ''];
    }

    /**
     * Whether the public site shows the section: on, with at least one active
     * offer. The same rule decides GET /offers' `null` and whether a banner
     * button to the offers is kept.
     *
     * @param  array{isEnabled: bool}|null  $settings  already loaded settings (saves a query)
     */
    public static function isShown(?array $settings = null): bool
    {
        return ($settings ?? self::get())['isEnabled'] && Offer::query()->where('is_active', true)->exists();
    }

    /**
     * Saves the fields that were sent (PATCH: a missing field doesn't change).
     * `menuCardImage` is a URL from the request: null removes the photo.
     *
     * @param  array{isEnabled?: bool, eyebrow?: string, title?: string, menuCardImage?: ?string}  $changes
     * @return array{isEnabled: bool, eyebrow: string, title: string, menuCardImagePath?: string}
     */
    public static function update(array $changes): array
    {
        $settings = self::get();

        foreach (['isEnabled', 'eyebrow', 'title'] as $field) {
            if (array_key_exists($field, $changes)) {
                $settings[$field] = $changes[$field];
            }
        }

        if (array_key_exists('menuCardImage', $changes)) {
            unset($settings['menuCardImagePath']);

            if ($changes['menuCardImage'] !== null) {
                $settings['menuCardImagePath'] = PublicImage::pathFromUrl($changes['menuCardImage']);
            }
        }

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $settings]);

        return $settings;
    }
}
