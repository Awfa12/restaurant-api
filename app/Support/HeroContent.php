<?php

namespace App\Support;

/**
 * The hero's texts: key `hero`, stored as her `HeroContent`
 * ({ badge?, headline, primaryActionLabel, secondaryActionLabel, features }),
 * saved whole (PUT). The button links stay in the frontend config.
 */
class HeroContent extends SectionSettings
{
    public const KEY = 'hero';

    protected static function key(): string
    {
        return self::KEY;
    }

    protected static function defaults(): array
    {
        return ['headline' => '', 'primaryActionLabel' => '', 'secondaryActionLabel' => '', 'features' => []];
    }

    protected static function fields(): array
    {
        return [];
    }
}
