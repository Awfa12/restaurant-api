<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /admin/{offers|banners}/{id}: the on/off switch from a list
 * (frontend setOfferActive / setBannerActive, API-CONTRACT §2). Only
 * `isActive`; other fields are ignored.
 */
class PatchIsActiveRequest extends FormRequest
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
