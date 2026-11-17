<?php

namespace App\Support;

/**
 * «من نحن»: key `about`, stored as { eyebrow, title, paragraphs, closing,
 * imagePath, imageAlt, badge? } (the photo as a path), saved whole (PUT).
 */
class AboutContent extends SectionSettings
{
    public const KEY = 'about';

    protected static function key(): string
    {
        return self::KEY;
    }

    protected static function defaults(): array
    {
        return ['eyebrow' => '', 'title' => '', 'paragraphs' => [], 'closing' => '', 'imageAlt' => ''];
    }

    protected static function fields(): array
    {
        return [];
    }
}
