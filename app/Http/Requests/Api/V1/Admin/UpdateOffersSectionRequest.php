<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\MarkedText;
use App\Rules\OwnUploadUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /admin/offers-section: the switch, texts or menu card photo (frontend
 * updateOffersSettings, checkSectionText). Partial: only what is sent changes;
 * `menuCardImage: null` removes the photo.
 */
class UpdateOffersSectionRequest extends FormRequest
{
    /** The frontend's SECTION_TEXT_LIMITS (the title counted without the ** markers). */
    public const LIMITS = [
        'eyebrow' => 30,
        'title' => 40,
    ];

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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...self::sectionTextRules(),
            'menuCardImage' => ['sometimes', 'nullable', new OwnUploadUrl],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::sectionTextMessages();
    }

    /**
     * The switch, eyebrow and title of a home section (frontend checkSectionText):
     * shared by the offers and reviews sections (and later About).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function sectionTextRules(): array
    {
        return [
            'isEnabled' => ['sometimes', 'required', 'boolean'],
            'eyebrow' => ['sometimes', 'required', 'string', 'max:'.self::LIMITS['eyebrow']],
            'title' => ['sometimes', 'required', 'string', new MarkedText(
                self::LIMITS['title'],
                'العنوان لازم يكون '.self::LIMITS['title'].' حرف أو أقل',
                emptyMessage: 'اكتب العنوان',
            )],
        ];
    }

    /**
     * The Arabic texts for sectionTextRules().
     *
     * @return array<string, string>
     */
    public static function sectionTextMessages(): array
    {
        return [
            'eyebrow.required' => 'اكتب العنوان الصغير',
            'eyebrow.string' => 'اكتب العنوان الصغير',
            'eyebrow.max' => 'لازم يكون '.self::LIMITS['eyebrow'].' حرف أو أقل',
            'title.required' => 'اكتب العنوان',
            'title.string' => 'اكتب العنوان',
        ];
    }
}
