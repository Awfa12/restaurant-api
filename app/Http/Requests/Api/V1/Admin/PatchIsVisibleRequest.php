<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /admin/reviews/{id}: shown or hidden from the list (frontend
 * setReviewVisible, API-CONTRACT §2). Only `isVisible`; other fields are ignored.
 */
class PatchIsVisibleRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'isVisible' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
