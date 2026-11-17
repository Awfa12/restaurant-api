<?php

namespace App\Support;

use App\Models\Review;

/**
 * The reviews section's settings: key `reviews_section`, stored as
 * { isEnabled, eyebrow, title, defaultQuestion }.
 */
class ReviewsSection extends SectionSettings
{
    public const KEY = 'reviews_section';

    /**
     * Whether the public site shows the section: on, with at least one visible
     * review (as the frontend's visibleReviewsOnly).
     *
     * @param  array{isEnabled: bool}|null  $settings  already loaded settings (saves a query)
     */
    public static function isShown(?array $settings = null): bool
    {
        return ($settings ?? self::get())['isEnabled'] && Review::query()->where('is_visible', true)->exists();
    }

    protected static function key(): string
    {
        return self::KEY;
    }

    protected static function defaults(): array
    {
        return ['isEnabled' => false, 'eyebrow' => '', 'title' => '', 'defaultQuestion' => ''];
    }

    protected static function fields(): array
    {
        return ['isEnabled', 'eyebrow', 'title', 'defaultQuestion'];
    }
}
