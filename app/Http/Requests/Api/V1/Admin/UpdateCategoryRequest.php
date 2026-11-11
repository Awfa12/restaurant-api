<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Category;
use App\Rules\OwnUploadUrl;
use App\Support\Slugs;

/**
 * PATCH /admin/categories/{id} { name?, slug?, image? }: only the fields sent
 * change; `image: null` removes the photo (API-CONTRACT §2 Partial PATCH).
 */
class UpdateCategoryRequest extends StoreCategoryRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:'.self::NAME_MAX, $this->uniqueName()],
            'slug' => Slugs::rules(['categories'], $this->ignoredIds()),
            'image' => ['nullable', new OwnUploadUrl],
        ];
    }

    /**
     * The category being edited.
     *
     * @return array<string, int>
     */
    protected function ignoredIds(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return ['categories' => $category->id];
    }
}
