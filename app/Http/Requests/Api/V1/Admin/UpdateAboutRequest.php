<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Admin\Concerns\ChecksContentImage;
use App\Rules\MarkedText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /admin/about: «من نحن», all together (frontend saveAbout,
 * checkAboutInput; 422 keys as API-CONTRACT §4 About, `paragraphs.<i>`).
 * Lengths count the text without the ** markers. A missing badge is cleared.
 */
class UpdateAboutRequest extends FormRequest
{
    use ChecksContentImage;

    /** The frontend's ABOUT_LIMITS (+ the photo's alt text column). */
    public const LIMITS = [
        'eyebrow' => 40,
        'title' => 40,
        'paragraphs' => 4,
        'paragraph' => 320,
        'closing' => 120,
        'badge' => 24,
        'imageAlt' => 120,
    ];

    public const PARAGRAPHS_REQUIRED = 'اكتب فقرة وحدة على الأقل';

    public const PARAGRAPH_EMPTY = 'الفقرة فاضية، اكتبها أو احذفها';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. Eyebrow and title
     * are the home sections' rules (checkSectionText) with About's limits.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...UpdateOffersSectionRequest::sectionTextRules(self::LIMITS, partial: false),
            'paragraphs' => ['required', 'array', 'min:1', 'max:'.self::LIMITS['paragraphs']],
            'paragraphs.*' => ['required', 'string', new MarkedText(
                self::LIMITS['paragraph'],
                'الفقرة '.self::LIMITS['paragraph'].' حرف أو أقل',
                emptyMessage: self::PARAGRAPH_EMPTY,
            )],
            'closing' => ['required', 'string', new MarkedText(
                self::LIMITS['closing'],
                'لازم تكون '.self::LIMITS['closing'].' حرف أو أقل',
                emptyMessage: 'اكتب الجملة الأخيرة',
            )],
            'image' => ['required', 'array'],
            'image.src' => ['nullable'],
            'image.alt' => ['nullable', 'string', 'max:'.self::LIMITS['imageAlt']],
            'badge' => ['nullable', 'string', 'max:'.self::LIMITS['badge']],
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
            ...UpdateOffersSectionRequest::sectionTextMessages(self::LIMITS),
            'paragraphs.required' => self::PARAGRAPHS_REQUIRED,
            'paragraphs.array' => self::PARAGRAPHS_REQUIRED,
            'paragraphs.min' => self::PARAGRAPHS_REQUIRED,
            'paragraphs.max' => 'أقصى شي '.self::LIMITS['paragraphs'].' فقرات',
            'paragraphs.*.required' => self::PARAGRAPH_EMPTY,
            'paragraphs.*.string' => self::PARAGRAPH_EMPTY,
            'closing.required' => 'اكتب الجملة الأخيرة',
            'closing.string' => 'اكتب الجملة الأخيرة',
            'image.required' => '«من نحن» بدّه صورة',
            'image.array' => '«من نحن» بدّه صورة',
            'badge.string' => 'الشارة '.self::LIMITS['badge'].' حرف أو أقل',
            'badge.max' => 'الشارة '.self::LIMITS['badge'].' حرف أو أقل',
        ];
    }

    /**
     * The photo: required, one of our uploads, error on `image`.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->checkContentImage(
                $validator,
                $validator->getData()['image']['src'] ?? null,
                '«من نحن» بدّه صورة',
            ),
        ];
    }
}
