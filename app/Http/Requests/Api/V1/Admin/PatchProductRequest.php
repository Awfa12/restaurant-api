<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PATCH /admin/products/{id}: the quick changes from the product list
 * (decision 4.2; frontend checkProductQuickChanges). Only these fields; the
 * ones not sent don't change.
 */
class PatchProductRequest extends FormRequest
{
    /** The quick toggle's text (it adds what to do, unlike the form's). */
    public const BEST_SELLER_NEEDS_IMAGE = '«الأكثر مبيعاً» بدّه صورة للصنف. ضيف صورة أول.';

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
            'price' => ['sometimes', 'required', 'integer', 'min:1', 'max:'.SaveProductRequest::MAX_PRICE],
            'isAvailable' => ['sometimes', 'required', 'boolean'],
            'isBestSeller' => ['sometimes', 'required', 'boolean'],
            // The undo of «شيل من الأكثر مبيعاً» sends the old place minus 0.5 (validation.ts undoBestSellerPlace).
            'bestSellerOrder' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return SaveProductRequest::priceMessages();
    }

    /**
     * A best seller needs a photo; a new price must leave every size above zero.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Product $product */
                $product = $this->route('product');
                $data = $validator->getData();

                if (($data['isBestSeller'] ?? false) === true && $product->image_path === null) {
                    $validator->errors()->add('isBestSeller', self::BEST_SELLER_NEEDS_IMAGE);
                }

                $price = filter_var($data['price'] ?? null, FILTER_VALIDATE_INT);
                $cheapestDelta = $product->sizes()->min('price_delta');

                if ($price !== false && $cheapestDelta !== null && $price + (int) $cheapestDelta <= 0) {
                    $validator->errors()->add('price', 'في حجم سعره بيصير صفر أو أقل بهالسعر، عدّل الأحجام من صفحة الصنف');
                }
            },
        ];
    }
}
