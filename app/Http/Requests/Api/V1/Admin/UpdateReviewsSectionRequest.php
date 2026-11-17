<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /admin/reviews-section: the switch, texts or default question
 * (frontend updateReviewsSettings, checkReviewsSettings). Partial: only what
 * is sent changes. Eyebrow and title follow the same checkSectionText rules
 * as the offers section.
 */
class UpdateReviewsSectionRequest extends FormRequest
{
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
            ...UpdateOffersSectionRequest::sectionTextRules(),
            'defaultQuestion' => ['sometimes', 'required', 'string', 'max:'.SaveReviewRequest::LIMITS['question']],
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
            ...UpdateOffersSectionRequest::sectionTextMessages(),
            'defaultQuestion.required' => 'اكتب السؤال اللي بيطلع فوق كل رأي',
            'defaultQuestion.string' => 'اكتب السؤال اللي بيطلع فوق كل رأي',
            'defaultQuestion.max' => 'السؤال '.SaveReviewRequest::LIMITS['question'].' حرف أو أقل',
        ];
    }
}
