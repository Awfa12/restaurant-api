<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\FeatureIcon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /admin/hero: the hero's texts, all together (frontend saveHero,
 * checkHeroInput; 422 keys as API-CONTRACT §4 Hero, `features.<i>.label` /
 * `.icon`). A missing badge is cleared.
 */
class UpdateHeroRequest extends FormRequest
{
    /** The frontend's HERO_LIMITS. */
    public const LIMITS = [
        'badge' => 24,
        'headline' => 32,
        'actionLabel' => 14,
        'features' => 3,
        'featureLabel' => 28,
    ];

    public const FEATURES_COUNT = 'الميزات لازم تكون تلاتة';

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
        $actionLabel = ['required', 'string', 'max:'.self::LIMITS['actionLabel']];

        return [
            'badge' => ['nullable', 'string', 'max:'.self::LIMITS['badge']],
            'headline' => ['required', 'string', 'max:'.self::LIMITS['headline']],
            'primaryActionLabel' => $actionLabel,
            'secondaryActionLabel' => $actionLabel,
            'features' => ['required', 'array', 'size:'.self::LIMITS['features']],
            'features.*' => ['array'],
            'features.*.label' => ['required', 'string', 'max:'.self::LIMITS['featureLabel']],
            'features.*.icon' => ['required', Rule::enum(FeatureIcon::class)],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $actionLabelMessages = fn (string $field) => [
            "{$field}.required" => 'اكتب شو مكتوب على الزر',
            "{$field}.string" => 'اكتب شو مكتوب على الزر',
            "{$field}.max" => 'نص الزر '.self::LIMITS['actionLabel'].' حرف أو أقل',
        ];

        return [
            'badge.string' => 'الشارة '.self::LIMITS['badge'].' حرف أو أقل',
            'badge.max' => 'الشارة '.self::LIMITS['badge'].' حرف أو أقل',
            'headline.required' => 'اكتب الجملة الرئيسية',
            'headline.string' => 'اكتب الجملة الرئيسية',
            'headline.max' => 'لازم تكون '.self::LIMITS['headline'].' حرف أو أقل',
            ...$actionLabelMessages('primaryActionLabel'),
            ...$actionLabelMessages('secondaryActionLabel'),
            'features.required' => self::FEATURES_COUNT,
            'features.array' => self::FEATURES_COUNT,
            'features.size' => self::FEATURES_COUNT,
            'features.*.label.required' => 'اكتب الميزة',
            'features.*.label.string' => 'اكتب الميزة',
            'features.*.label.max' => self::LIMITS['featureLabel'].' حرف أو أقل',
            'features.*.icon.required' => 'اختار أيقونة',
            'features.*.icon.enum' => 'اختار أيقونة',
        ];
    }
}
