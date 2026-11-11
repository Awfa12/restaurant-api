<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\MoveDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /admin/{resource}/{id}/move { direction: "up" | "down" } (API-CONTRACT §4.2).
 */
class MoveRequest extends FormRequest
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
            'direction' => ['required', Rule::enum(MoveDirection::class)],
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
            'direction.required' => 'الاتجاه لازم يكون up أو down',
            'direction.enum' => 'الاتجاه لازم يكون up أو down',
        ];
    }

    /**
     * The validated direction.
     */
    public function direction(): MoveDirection
    {
        return MoveDirection::from($this->validated('direction'));
    }
}
