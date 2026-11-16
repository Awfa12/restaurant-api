<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /admin/offers/{id}: the on/off switch from the list (frontend
 * setOfferActive). Only `isActive`; other fields are ignored.
 */
class PatchOfferRequest extends FormRequest
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
            'isActive' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
