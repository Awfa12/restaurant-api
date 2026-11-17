<?php

namespace App\Enums;

/**
 * The social platforms the footer links to (frontend `SocialPlatform`), with
 * the domains a profile link may use (frontend lib/site-info SOCIAL_PLATFORMS).
 */
enum SocialPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Threads = 'threads';
    case Tiktok = 'tiktok';

    /**
     * The name shown in messages ("هالرابط مش لصفحة على Instagram").
     */
    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Threads => 'Threads',
            self::Tiktok => 'TikTok',
        };
    }

    /**
     * The domains a profile link on this platform may use.
     *
     * @return array<int, string>
     */
    public function hosts(): array
    {
        return match ($this) {
            self::Instagram => ['instagram.com'],
            self::Facebook => ['facebook.com', 'fb.com', 'fb.me'],
            self::Threads => ['threads.net', 'threads.com'],
            self::Tiktok => ['tiktok.com'],
        };
    }
}
