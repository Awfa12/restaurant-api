<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /admin/uploads (multipart `image`, API-CONTRACT §4 Images): WebP, PNG
 * or JPG checked by content, ≤ 5 MB, ≤ 4000 px per side. Never SVG (it can
 * carry scripts).
 */
class StoreUploadRequest extends FormRequest
{
    /** API-CONTRACT §2: max 5 MB. */
    public const MAX_KILOBYTES = 5 * 1024;

    /** Larger sides are refused (also keeps "decompression bomb" images out). */
    public const MAX_SIDE_PIXELS = 4000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'image' => [
                // One clear message: a file that isn't an image would also fail the size checks.
                'bail',
                'required',
                'file',
                // mimes checks the file's content (not its name); SVG isn't in the list
                'mimes:webp,png,jpg,jpeg',
                'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_SIDE_PIXELS.',max_height='.self::MAX_SIDE_PIXELS,
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'اختار صورة',
            'image.file' => 'ما قدرنا نرفع الصورة، جرّب مرة تانية',
            'image.uploaded' => 'ما قدرنا نرفع الصورة، جرّب مرة تانية',
            'image.mimes' => 'الصورة لازم تكون WebP أو PNG أو JPG',
            'image.max' => 'الصورة كبيرة كتير (أكتر من 5MB)، اختار صورة أصغر',
            'image.dimensions' => 'الصورة كبيرة كتير (أكتر من '.self::MAX_SIDE_PIXELS.' بكسل)، اختار صورة أصغر',
        ];
    }
}
