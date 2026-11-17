<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\BannerTargetKind;
use App\Models\Category;
use App\Models\Product;
use App\Rules\OwnUploadUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole slide: POST /admin/banners and PUT /admin/banners/{id}. Rules and
 * Arabic texts from the frontend's checkBannerInput; 422 keys as API-CONTRACT
 * §4 Banners (`eyebrow`, `title`, `image`, `action.label`, `action.target`).
 */
class SaveBannerRequest extends FormRequest
{
    /** The frontend's BANNER_LIMITS (+ the photo's alt text column). */
    public const LIMITS = [
        'eyebrow' => 24,
        'titleLine' => 22,
        'titleLines' => 2,
        'actionLabel' => 16,
        'imageAlt' => 120,
    ];

    public const IMAGE_REQUIRED = 'الشريحة بدها صورة';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The checks that
     * need several fields (title lines, photo, button) are in after(), so
     * their errors land on the contract's keys.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:'.self::LIMITS['eyebrow']],
            'title' => ['required', 'string'],
            'image' => ['required', 'array'],
            'image.src' => ['nullable'],
            'image.alt' => ['nullable', 'string', 'max:'.self::LIMITS['imageAlt']],
            'action' => ['nullable', 'array'],
            'action.label' => ['nullable'],
            'action.target' => ['nullable'],
            'action.target.kind' => ['nullable'],
            'action.target.categoryId' => ['nullable'],
            'action.target.productId' => ['nullable'],
            'isActive' => ['required', 'boolean'],
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
            'eyebrow.string' => 'لازم يكون '.self::LIMITS['eyebrow'].' حرف أو أقل',
            'eyebrow.max' => 'لازم يكون '.self::LIMITS['eyebrow'].' حرف أو أقل',
            'title.required' => 'اكتب العنوان',
            'title.string' => 'اكتب العنوان',
            'image.required' => self::IMAGE_REQUIRED,
            'image.array' => self::IMAGE_REQUIRED,
        ];
    }

    /**
     * One kind of line break, so the two-line rule and the stored title agree.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title'))) {
            $this->merge(['title' => str_replace(["\r\n", "\r"], "\n", $this->input('title'))]);
        }
    }

    /**
     * The checks across fields, with the frontend's keys and texts.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->getData();

                $this->checkTitle($validator, $data['title'] ?? null);
                $this->checkImage($validator, $data['image']['src'] ?? null);

                if (is_array($data['action'] ?? null)) {
                    $this->checkAction($validator, $data['action']);
                }
            },
        ];
    }

    /**
     * At most two lines of at most 22 characters (the text column is narrow on phones).
     */
    protected function checkTitle(Validator $validator, mixed $title): void
    {
        if (! is_string($title) || trim($title) === '') {
            return;
        }

        $lines = explode("\n", $title);

        if (count($lines) > self::LIMITS['titleLines']) {
            $validator->errors()->add('title', 'العنوان سطرين بالكتير');
        } elseif (collect($lines)->contains(fn (string $line) => mb_strlen($line) > self::LIMITS['titleLine'])) {
            $validator->errors()->add('title', 'كل سطر '.self::LIMITS['titleLine'].' حرف أو أقل، لحتى يبيّن على الموبايل');
        }
    }

    /**
     * A photo is required, and it must be one of our uploads (error on `image`).
     */
    protected function checkImage(Validator $validator, mixed $src): void
    {
        if ($validator->errors()->has('image')) {
            return;
        }

        if (blank($src)) {
            $validator->errors()->add('image', self::IMAGE_REQUIRED);

            return;
        }

        if (! OwnUploadUrl::accepts($src)) {
            $validator->errors()->add('image', OwnUploadUrl::MESSAGE);
        }
    }

    /**
     * The button: a label, and a target that exists (a gone one, sent as `""`, is refused).
     *
     * @param  array<string, mixed>  $action
     */
    protected function checkAction(Validator $validator, array $action): void
    {
        $label = $action['label'] ?? null;

        if (! is_string($label) || trim($label) === '') {
            $validator->errors()->add('action.label', 'اكتب شو مكتوب على الزر');
        } elseif (mb_strlen($label) > self::LIMITS['actionLabel']) {
            $validator->errors()->add('action.label', 'نص الزر '.self::LIMITS['actionLabel'].' حرف أو أقل');
        }

        $target = is_array($action['target'] ?? null) ? $action['target'] : [];
        $kind = BannerTargetKind::tryFrom(is_string($target['kind'] ?? null) ? $target['kind'] : '');

        $error = match ($kind) {
            BannerTargetKind::Offers => null,
            BannerTargetKind::Category => $this->slugExists(Category::class, $target['categoryId'] ?? null) ? null : 'اختار القسم',
            BannerTargetKind::Product => $this->slugExists(Product::class, $target['productId'] ?? null) ? null : 'اختار الصنف',
            null => 'اختار وين بياخد الزر',
        };

        if ($error !== null) {
            $validator->errors()->add('action.target', $error);
        }
    }

    /**
     * @param  class-string<Category|Product>  $model
     */
    protected function slugExists(string $model, mixed $slug): bool
    {
        return is_string($slug) && $slug !== '' && $model::where('slug', $slug)->exists();
    }
}
