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
        $path = is_string($value) ? PublicImage::pathFromUrl($value) : null;

        if ($path === null || ! Upload::where('path', $path)->exists()) {
            $fail(self::MESSAGE);
        }
    }
}
