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
            'isEnabled' => ['sometimes', 'required', 'boolean'],
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
     * The eyebrow and title of a home section (frontend checkSectionText):
     * shared by the offers, reviews and About sections.
     *
     * @param  array{eyebrow: int, title: int}  $limits  About allows a longer eyebrow
     * @param  bool  $partial  PATCH (a missing field doesn't change) or a whole save (PUT: both required)
     * @return array<string, array<int, mixed>>
     */
    public static function sectionTextRules(array $limits = self::LIMITS, bool $partial = true): array
    {
        $presence = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'eyebrow' => [...$presence, 'string', 'max:'.$limits['eyebrow']],
            'title' => [...$presence, 'string', new MarkedText(
                $limits['title'],
                'العنوان لازم يكون '.$limits['title'].' حرف أو أقل',
                emptyMessage: 'اكتب العنوان',
            )],
        ];
    }

    /**
     * The Arabic texts for sectionTextRules().
     *
     * @param  array{eyebrow: int, title: int}  $limits
     * @return array<string, string>
     */
    public static function sectionTextMessages(array $limits = self::LIMITS): array
    {
        return [
            'eyebrow.required' => 'اكتب العنوان الصغير',
            'eyebrow.string' => 'اكتب العنوان الصغير',
            'eyebrow.max' => 'لازم يكون '.$limits['eyebrow'].' حرف أو أقل',
            'title.required' => 'اكتب العنوان',
            'title.string' => 'اكتب العنوان',
        ];
    }
}
