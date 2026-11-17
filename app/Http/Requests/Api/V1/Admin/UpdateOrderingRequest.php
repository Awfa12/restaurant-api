<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Branch;
use App\Models\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /admin/ordering: the whole ordering settings (frontend saveOrdering,
 * checkOrderingInput; 422 keys as API-CONTRACT §4 Ordering). Checked against
 * the saved restaurant info: orders go out on WhatsApp, so what's switched on
 * needs a WhatsApp number to go to.
 */
class UpdateOrderingRequest extends FormRequest
{
    /** The frontend's ORDERING_LIMITS (MAX_TABLES = 99). */
    public const LIMITS = [
        'maxTables' => 99,
        'greeting' => 120,
        'closing' => 200,
        'thankYou' => 240,
    ];

    /** The only placeholders the texts may use (frontend lib/order fillTemplate). */
    public const PLACEHOLDERS = ['{restaurant}', '{name}'];

    public const FEE = 'اكتب رسوم التوصيل رقم، متل 150 (أو 0 إذا مجاني)';

    public const TABLE_COUNT = 'عدد الطاولات من 1 لـ 99';

    public const TEXT_REQUIRED = 'هالنص ما بيصير يكون فاضي';

    public const BRANCH_UNKNOWN = 'في فرع مش موجود، حدّث الصفحة';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The checks against
     * the restaurant info and across fields are in after().
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'delivery' => ['required', 'array'],
            'delivery.isEnabled' => ['required', 'boolean'],
            'delivery.fee' => ['required', 'integer', 'min:0', 'max:'.SaveProductRequest::MAX_PRICE],
            'delivery.phoneId' => ['nullable'],
            'dineIn' => ['required', 'array'],
            'dineIn.isEnabled' => ['required', 'boolean'],
            'dineIn.branches' => ['present', 'array'],
            'dineIn.branches.*' => ['array'],
            'dineIn.branches.*.branchId' => ['nullable'],
            'dineIn.branches.*.isEnabled' => ['required', 'boolean'],
            'dineIn.branches.*.tableCount' => ['required', 'integer', 'min:0', 'max:'.self::LIMITS['maxTables']],
            'message' => ['required', 'array'],
            'message.greeting' => ['required', 'string', 'max:'.self::LIMITS['greeting']],
            'message.closing' => ['nullable', 'string', 'max:'.self::LIMITS['closing']],
            'thankYou' => ['required', 'string', 'max:'.self::LIMITS['thankYou']],
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
            'delivery.fee.required' => self::FEE,
            'delivery.fee.integer' => self::FEE,
            'delivery.fee.min' => self::FEE,
            'delivery.fee.max' => 'الرسوم كبيرة كتير، تأكد منها',
            'dineIn.branches.*.tableCount.required' => self::TABLE_COUNT,
            'dineIn.branches.*.tableCount.integer' => self::TABLE_COUNT,
            'dineIn.branches.*.tableCount.min' => self::TABLE_COUNT,
            'dineIn.branches.*.tableCount.max' => self::TABLE_COUNT,
            'message.greeting.required' => self::TEXT_REQUIRED,
            'message.greeting.string' => self::TEXT_REQUIRED,
            'message.greeting.max' => self::LIMITS['greeting'].' حرف أو أقل',
            'message.closing.max' => self::LIMITS['closing'].' حرف أو أقل',
            'thankYou.required' => self::TEXT_REQUIRED,
            'thankYou.string' => self::TEXT_REQUIRED,
            'thankYou.max' => self::LIMITS['thankYou'].' حرف أو أقل',
        ];
    }

    /**
     * The checks against the saved restaurant info, and across fields.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->getData();
                $errors = $validator->errors();

                $delivery = is_array($data['delivery'] ?? null) ? $data['delivery'] : [];
                if (($delivery['isEnabled'] ?? false) === true && ! self::isDeliveryPhone($delivery['phoneId'] ?? null)) {
                    $errors->add('delivery.phoneId', 'اختار رقم عليه واتساب لتوصل عليه طلبات التوصيل');
                }

                $dineIn = is_array($data['dineIn'] ?? null) ? $data['dineIn'] : [];
                if (is_array($dineIn['branches'] ?? null)) {
                    $this->checkBranches($validator, array_values($dineIn['branches']), ($dineIn['isEnabled'] ?? false) === true);
                }

                foreach (['message.greeting', 'message.closing', 'thankYou'] as $field) {
                    $text = data_get($data, $field);
                    if (is_string($text) && ! $errors->has($field) && self::hasUnknownPlaceholder($text)) {
                        $errors->add($field, 'استعمل زرّي «اسم المطعم» و«اسم الزبون» بدل ما تكتب أقواس { }');
                    }
                }
            },
        ];
    }

    /**
     * Whether the id is a restaurant phone (not a branch's) with WhatsApp:
     * where delivery orders may go.
     */
    public static function isDeliveryPhone(mixed $phoneId): bool
    {
        return is_scalar($phoneId) && ctype_digit((string) $phoneId)
            && Phone::query()->whereKey((int) $phoneId)->whereNull('branch_id')->where('has_whatsapp', true)->exists();
    }

    /**
     * Whether the text uses a {…} other than {restaurant} and {name}.
     */
    public static function hasUnknownPlaceholder(string $text): bool
    {
        preg_match_all('/\{[^{}]*\}/u', $text, $matches);

        return collect($matches[0])->contains(fn (string $token) => ! in_array($token, self::PLACEHOLDERS, true));
    }

    /**
     * Each setting for a saved branch, once; a branch that's on has tables
     * and a WhatsApp phone of its own; dine-in on needs a branch on.
     *
     * @param  array<int, mixed>  $settings
     */
    protected function checkBranches(Validator $validator, array $settings, bool $dineInEnabled): void
    {
        $errors = $validator->errors();
        $branches = Branch::query()
            ->withExists(['phones as has_whatsapp_phone' => fn ($query) => $query->where('has_whatsapp', true)])
            ->get()
            ->keyBy('slug');
        $seen = [];

        foreach ($settings as $index => $setting) {
            $setting = is_array($setting) ? $setting : [];
            $branchId = $setting['branchId'] ?? null;
            $branch = is_string($branchId) ? $branches->get($branchId) : null;

            if ($branch === null || in_array($branchId, $seen, true)) {
                if (! $errors->has('dineIn')) {
                    $errors->add('dineIn', self::BRANCH_UNKNOWN);
                }
            }
            $seen[] = $branchId;

            $isEnabled = ($setting['isEnabled'] ?? false) === true;

            if ($isEnabled && ! $errors->has("dineIn.branches.{$index}.tableCount") && (int) ($setting['tableCount'] ?? 0) === 0) {
                $errors->add("dineIn.branches.{$index}.tableCount", 'اكتب كم طاولة بالفرع');
            }

            if ($isEnabled && $branch !== null && ! $branch->has_whatsapp_phone) {
                $errors->add("dineIn.branches.{$index}.isEnabled", 'هالفرع ما إلو رقم واتساب. ضيفه من «معلومات المطعم» أول');
            }
        }

        if ($dineInEnabled && ! collect($settings)->contains(fn ($setting) => is_array($setting) && ($setting['isEnabled'] ?? false) === true)) {
            $errors->add('dineIn', 'شغّل طاولات فرع واحد على الأقل، أو طفّي طلبات الطاولات');
        }
    }
}
