<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ImageStyle;
use App\Models\Offer;
use App\Rules\OwnUploadUrl;
use App\Support\Slugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole offer: POST /admin/offers and PUT /admin/offers/{id}. Rules and
 * Arabic texts from the frontend's checkOfferInput (and validatePrice, shared
 * with products); field keys as API-CONTRACT §4 Offers.
 */
class SaveOfferRequest extends FormRequest
{
    /** The frontend's OFFER_LIMITS. */
    public const LIMITS = [
        'title' => 30,
        'items' => 6,
        'item' => 40,
        'freeItem' => 40,
        'badge' => 20,
    ];

    public const ITEMS_REQUIRED = 'اكتب شو بياخد الزبون، متل: 4 سندويشات شاورما';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. Each item's length
     * is checked in after(), with the error on `items` as the frontend shows it.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => Slugs::rules(['products', 'offers'], $this->ignoredIds()),
            'title' => ['required', 'string', 'max:'.self::LIMITS['title']],
            'items' => ['required', 'array', 'max:'.self::LIMITS['items']],
            'freeItem' => ['required', 'string', 'max:'.self::LIMITS['freeItem']],
            'price' => ['required', 'integer', 'min:1', 'max:'.SaveProductRequest::MAX_PRICE],
            'image' => ['required', new OwnUploadUrl],
            'imageStyle' => ['nullable', Rule::enum(ImageStyle::class)],
            'badge' => ['nullable', 'string', 'max:'.self::LIMITS['badge']],
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
            'title.required' => 'اكتب اسم العرض',
            'title.string' => 'اكتب اسم العرض',
            'title.max' => 'الاسم لازم يكون '.self::LIMITS['title'].' حرف أو أقل',
            'items.required' => self::ITEMS_REQUIRED,
            'items.array' => self::ITEMS_REQUIRED,
            'items.max' => 'أقصى شي '.self::LIMITS['items'].' أشياء',
            'freeItem.required' => 'اكتب شو بياخد مجاناً',
            'freeItem.string' => 'اكتب شو بياخد مجاناً',
            'freeItem.max' => 'لازم يكون '.self::LIMITS['freeItem'].' حرف أو أقل',
            'image.required' => 'العرض بدّه صورة',
            'badge.string' => 'الشارة لازم تكون '.self::LIMITS['badge'].' حرف أو أقل',
            'badge.max' => 'الشارة لازم تكون '.self::LIMITS['badge'].' حرف أو أقل',
            ...SaveProductRequest::priceMessages(),
            ...Slugs::messages(),
        ];
    }

    /**
     * Empty lines aren't items (her form drops them; the middleware turns "" into null).
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        if (is_array($items)) {
            $this->merge(['items' => array_values(array_filter($items, fn ($item) => filled($item)))]);
        }
    }

    /**
     * Every item is a text of at most 40 characters (error on `items`, as the frontend).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $items = $validator->getData()['items'] ?? null;

                if (! is_array($items) || count($items) > self::LIMITS['items']) {
                    return;
                }

                foreach ($items as $item) {
                    if (! is_string($item) || mb_strlen($item) > self::LIMITS['item']) {
                        $validator->errors()->add('items', 'كل شي لازم يكون '.self::LIMITS['item'].' حرف أو أقل');

                        return;
                    }
                }
            },
        ];
    }

    /**
     * The offer being saved, skipped by the slug check.
     *
     * @return array<string, int>
     */
    protected function ignoredIds(): array
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer ? ['offers' => $offer->id] : [];
    }
}
