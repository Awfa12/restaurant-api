<?php

namespace App\Support;

/**
 * The restaurant info's own texts: key `site_info`, stored as { tagline,
 * openingHours?, openingHoursSchema?, socialLinks? } (docs/DATABASE.md §3).
 * Phones and branches are their own tables; the whole info is saved by
 * App\Actions\SaveSiteInfo.
 */
class SiteInfoSettings extends SectionSettings
{
    public const KEY = 'site_info';

    protected static function key(): string
    {
        return self::KEY;
    }

    protected static function defaults(): array
    {
        return ['tagline' => ''];
    }

    protected static function fields(): array
    {
        return [];
    }
}
