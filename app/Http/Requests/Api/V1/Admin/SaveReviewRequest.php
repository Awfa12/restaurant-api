<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Rules\MarkedText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The whole review: POST /admin/reviews and PUT /admin/reviews/{id}. Rules and
 * Arabic texts from the frontend's checkReviewInput; 422 keys as API-CONTRACT
 * §4 Reviews.
 */
class SaveReviewRequest extends FormRequest
{
    /** The frontend's REVIEW_LIMITS (the answer counted without the ** markers). */
    public const LIMITS = [
        'customerName' => 20,
        'question' => 120,
        'answer' => 220,
        'time' => 12,
    ];

    public const TIME_INVALID = 'الوقت مش مفهوم، اختاره من الساعة';

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
            'customerName' => ['required', 'string', 'max:'.self::LIMITS['customerName']],
            'question' => ['nullable', 'string', 'max:'.self::LIMITS['question']],
            'answer' => ['required', 'string', new MarkedText(
                self::LIMITS['answer'],
                'الرد '.self::LIMITS['answer'].' حرف أو أقل، لحتى يبيّن كامل بالكرت',
                emptyMessage: 'اكتب شو قال الزبون',
            )],
            'time' => ['nullable', 'string', 'max:'.self::LIMITS['time']],
            'isVisible' => ['required', 'boolean'],
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
            'customerName.required' => 'اكتب اسم الزبون',
            'customerName.string' => 'اكتب اسم الزبون',
            'customerName.max' => 'الاسم '.self::LIMITS['customerName'].' حرف أو أقل',
            'question.string' => 'السؤال '.self::LIMITS['question'].' حرف أو أقل',
            'question.max' => 'السؤال '.self::LIMITS['question'].' حرف أو أقل',
            'answer.required' => 'اكتب شو قال الزبون',
            'answer.string' => 'اكتب شو قال الزبون',
            'time.string' => self::TIME_INVALID,
            'time.max' => self::TIME_INVALID,
        ];
    }
}
