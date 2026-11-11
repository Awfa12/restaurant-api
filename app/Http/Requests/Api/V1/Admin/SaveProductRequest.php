<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ImageStyle;
use App\Models\Product;
use App\Rules\OwnUploadUrl;
use App\Support\Slugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The whole product: POST /admin/products and PUT /admin/products/{id}
 * (decision 4.2). Rules and Arabic texts from the frontend's checkProductInput
 * and validatePrice; field keys as API-CONTRACT §5 (sizes:
 * optionGroups.0.options.<i>.<field>).
 */
class SaveProductRequest extends FormRequest
{
    /** The frontend's MAX_PRICE. */
    public const MAX_PRICE = 10_000_000;

    /** The frontend's PRODUCT_LIMITS (+ the size group's label). */
    public const LIMITS = [
        'name' => 40,
        'description' => 160,
        'ingredients' => 10,
        'ingredient' => 30,
        'sizeName' => 24,
        'sizeShortName' => 10,
        'sizesLabel' => 50,
    ];

    public const BEST_SELLER_NEEDS_IMAGE = '«الأكثر مبيعاً» بدّه صورة للصنف';

    public const SIZE_NOT_OF_THIS_PRODUCT = 'هالحجم مش تابع لهالصنف، حدّث الصفحة';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The checks across
     * fields (sizes, best seller photo) are in after().
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'slug' => Slugs::rules(['products', 'offers'], $this->ignoredIds()),
            'categoryId' => ['required', 'string', 'exists:categories,slug'],
            'name' => ['required', 'string', 'max:'.self::LIMITS['name']],
            'description' => ['nullable', 'string', 'max:'.self::LIMITS['description']],
            'ingredients' => ['present', 'array', 'max:'.self::LIMITS['ingredients']],
            'price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PRICE],
            'image' => ['nullable', new OwnUploadUrl],
            'imageStyle' => ['nullable', Rule::enum(ImageStyle::class)],
            'isAvailable' => ['required', 'boolean'],
            'isBestSeller' => ['required', 'boolean'],
            'bestSellerOrder' => ['nullable', 'numeric', 'min:0'],
            'optionGroups' => ['nullable', 'array', 'max:1'],
            'optionGroups.0.name' => ['nullable', 'string', 'max:'.self::LIMITS['sizesLabel']],
            'optionGroups.0.options' => ['nullable', 'array'],
            'optionGroups.0.options.*.id' => ['nullable'],
            'optionGroups.0.options.*.name' => ['nullable'],
            'optionGroups.0.options.*.shortName' => ['nullable'],
            'optionGroups.0.options.*.priceDelta' => ['nullable'],
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
            'categoryId.required' => 'اختار قسم للصنف',
            'categoryId.string' => 'اختار قسم للصنف',
            'categoryId.exists' => 'اختار قسم للصنف',
            'name.required' => 'اكتب اسم الصنف',
            'name.string' => 'اكتب اسم الصنف',
            'name.max' => 'الاسم لازم يكون '.self::LIMITS['name'].' حرف أو أقل',
            'description.max' => 'الوصف لازم يكون '.self::LIMITS['description'].' حرف أو أقل',
            'ingredients.max' => 'أقصى شي '.self::LIMITS['ingredients'].' مكونات (أكتر من هيك ما بيبيّنوا منيح عالموبايل)',
            ...self::priceMessages(),
            ...Slugs::messages(),
        ];
    }

    /**
     * The frontend's validatePrice texts.
     *
     * @return array<string, string>
     */
    public static function priceMessages(): array
    {
        return [
            'price.required' => 'السعر لازم يكون رقم، متل 250',
            'price.integer' => 'السعر لازم يكون رقم، متل 250',
            'price.min' => 'السعر لازم يكون أكبر من صفر',
            'price.max' => 'السعر كبير كتير، تأكد منه',
        ];
    }

    /**
     * Checks across fields, with the frontend's keys and texts.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->getData();

                $this->checkIngredients($validator, $data['ingredients'] ?? null);

                if (($data['isBestSeller'] ?? false) === true && blank($data['image'] ?? null)) {
                    $validator->errors()->add('isBestSeller', self::BEST_SELLER_NEEDS_IMAGE);
                }

                $options = $data['optionGroups'][0]['options'] ?? null;

                if (is_array($options) && $options !== []) {
                    $this->checkSizes($validator, array_values($options), $data['price'] ?? null);
                }
            },
        ];
    }

    /**
     * Empty lines aren't ingredients (the middleware turns "" into null).
     */
    protected function prepareForValidation(): void
    {
        $ingredients = $this->input('ingredients');

        if (is_array($ingredients)) {
            $this->merge(['ingredients' => array_values(array_filter($ingredients, fn ($item) => filled($item)))]);
        }
    }

    /**
     * Every ingredient is a text of at most 30 characters (error on `ingredients`, as the frontend).
     */
    protected function checkIngredients(Validator $validator, mixed $ingredients): void
    {
        if (! is_array($ingredients) || count($ingredients) > self::LIMITS['ingredients']) {
            return;
        }

        foreach ($ingredients as $ingredient) {
            if (! is_string($ingredient) || mb_strlen($ingredient) > self::LIMITS['ingredient']) {
                $validator->errors()->add('ingredients', 'كل مكوّن لازم يكون '.self::LIMITS['ingredient'].' حرف أو أقل');

                return;
            }
        }
    }

    /**
     * The sizes (checkProductInput): 2 or more, names set, short and unique,
     * each full price (product price + difference) a valid price, and every
     * id one of this product's sizes.
     *
     * @param  array<int, mixed>  $options
     */
    protected function checkSizes(Validator $validator, array $options, mixed $price): void
    {
        $errors = $validator->errors();

        if (count($options) < 2) {
            $errors->add('optionGroups', 'الأحجام لازم تكون حجمين أو أكتر، أو شيلها');
        }

        $ownIds = $this->ownSizeIds();
        $seenNames = [];

        foreach ($options as $index => $option) {
            $field = fn (string $name) => "optionGroups.0.options.{$index}.{$name}";
            $option = is_array($option) ? $option : [];

            $name = is_string($option['name'] ?? null) ? trim($option['name']) : '';
            if ($name === '') {
                $errors->add($field('name'), 'اكتب اسم الحجم');
            } elseif (mb_strlen($name) > self::LIMITS['sizeName']) {
                $errors->add($field('name'), 'الاسم طويل');
            } elseif (in_array($name, $seenNames, true)) {
                $errors->add($field('name'), 'في حجم بنفس الاسم');
            }
            $seenNames[] = $name;

            $shortName = $option['shortName'] ?? null;
            if ($shortName !== null && (! is_string($shortName) || mb_strlen(trim($shortName)) > self::LIMITS['sizeShortName'])) {
                $errors->add($field('shortName'), 'الاسم المختصر '.self::LIMITS['sizeShortName'].' حروف أو أقل');
            }

            $delta = filter_var($option['priceDelta'] ?? null, FILTER_VALIDATE_INT);
            $basePrice = filter_var($price, FILTER_VALIDATE_INT);
            $sizePrice = $delta === false || $basePrice === false ? null : $basePrice + $delta;
            if ($delta === false || ($sizePrice !== null && ($sizePrice <= 0 || $sizePrice > self::MAX_PRICE))) {
                $errors->add($field('priceDelta'), 'سعر الحجم لازم يكون رقم أكبر من صفر');
            }

            $id = $option['id'] ?? null;
            if (filled($id) && ! in_array((string) $id, $ownIds, true)) {
                $errors->add($field('id'), self::SIZE_NOT_OF_THIS_PRODUCT);
            }
        }
    }

    /**
     * The ids (as strings) of the sizes this product already has; none for a new one.
     *
     * @return array<int, string>
     */
    protected function ownSizeIds(): array
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? $product->sizes()->pluck('id')->map(fn ($id) => (string) $id)->all()
            : [];
    }

    /**
     * The product being saved, skipped by the slug check.
     *
     * @return array<string, int>
     */
    protected function ignoredIds(): array
    {
        $product = $this->route('product');

        return $product instanceof Product ? ['products' => $product->id] : [];
    }
}
