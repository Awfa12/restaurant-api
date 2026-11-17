<?php

namespace App\Support;

use App\Models\Offer;

/**
 * The offers section's settings: key `offers_section`, stored as
 * { isEnabled, eyebrow, title, menuCardImagePath? } (photos as paths).
 */
class OffersSection extends SectionSettings
{
    public const KEY = 'offers_section';

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

    protected static function key(): string
    {
        return self::KEY;
    }

    protected static function defaults(): array
    {
        return ['isEnabled' => false, 'eyebrow' => '', 'title' => ''];
    }

    protected static function fields(): array
    {
        return ['isEnabled', 'eyebrow', 'title'];
    }

    /**
     * `menuCardImage` is a URL from the request, stored as a path; null removes the photo.
     */
    protected static function applyChanges(array $settings, array $changes): array
    {
        if (array_key_exists('menuCardImage', $changes)) {
            unset($settings['menuCardImagePath']);

            if ($changes['menuCardImage'] !== null) {
                $settings['menuCardImagePath'] = PublicImage::pathFromUrl($changes['menuCardImage']);
            }
        }

        return $settings;
    }
}
