<?php

namespace App\Rules;

use App\Models\Upload;
use App\Support\PublicImage;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An image field must be the URL of one of our uploads (POST /admin/uploads),
 * never a link to another host (decision 4.3). The FormRequest then stores
 * PublicImage::pathFromUrl() of it.
 */
class OwnUploadUrl implements ValidationRule
{
    /** Shown under the image field. */
    public const MESSAGE = 'الصورة مش من الصور المرفوعة، ارفعها من جديد';

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::accepts($value)) {
            $fail(self::MESSAGE);
        }
    }

    /**
     * Whether the value is the URL of one of our uploads (for checks that
     * report on another key, e.g. a banner's `image.src` on `image`).
     */
    public static function accepts(mixed $value): bool
    {
        $path = is_string($value) ? PublicImage::pathFromUrl($value) : null;

        return $path !== null && Upload::where('path', $path)->exists();
    }
}
