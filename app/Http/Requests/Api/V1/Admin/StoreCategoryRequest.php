<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Support\Slugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /admin/categories { name, slug? } (frontend validateCategoryName).
 */
class StoreCategoryRequest extends FormRequest
{
    /** The frontend's CATEGORY_NAME_MAX. */
    public const NAME_MAX = 24;

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
            'name' => ['required', 'string', 'max:'.self::NAME_MAX, $this->uniqueName()],
            'slug' => Slugs::rules(['categories'], $this->ignoredIds()),
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
            'name.required' => 'اكتب اسم القسم',
            'name.string' => 'اكتب اسم القسم',
            'name.max' => 'الاسم لازم يكون '.self::NAME_MAX.' حرف أو أقل',
            'name.unique' => 'في قسم بنفس الاسم',
            ...Slugs::messages(),
        ];
    }

    /**
     * Names are compared trimmed with spaces collapsed ("  سندويشات " = "سندويشات"),
     * so they are stored that way.
     */
    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', $name) ?? $name)]);
        }
    }

    /**
     * Unique among the categories (the collation also ignores letter case).
     */
    protected function uniqueName(): mixed
    {
        return Rule::unique('categories', 'name')->ignore($this->ignoredIds()['categories'] ?? null);
    }

    /**
     * The record being edited, skipped by the unique checks.
     *
     * @return array<string, int>
     */
    protected function ignoredIds(): array
    {
        return [];
    }
}
