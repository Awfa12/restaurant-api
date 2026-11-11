<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Images are stored as a path on the public disk (docs/DATABASE.md §1) and
 * sent as a full URL. This is the one place that converts between the two,
 * so a host change (staging, production, the domain) never touches the data.
 */
class PublicImage
{
    /** The folder uploads are stored in, on the public disk. */
    public const UPLOADS_FOLDER = 'uploads';

    /**
     * The public URL of a stored path, e.g. https://api.<domain>/storage/uploads/x.webp.
     */
    public static function url(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    /**
     * The stored path behind one of our public URLs, or null when the URL
     * isn't ours (another host, outside the uploads folder, or "..").
     */
    public static function pathFromUrl(string $url): ?string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        if (! str_starts_with($url, $base)) {
            return null;
        }

        $path = rawurldecode(substr($url, strlen($base)));

        $isInUploads = str_starts_with($path, self::UPLOADS_FOLDER.'/');
        $hasNoTraversal = ! str_contains($path, '..') && ! str_contains($path, '\\');

        return $isInUploads && $hasNoTraversal ? $path : null;
    }
}
