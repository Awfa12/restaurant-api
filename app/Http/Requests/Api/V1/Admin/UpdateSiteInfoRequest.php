<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\SocialPlatform;
use App\Models\Branch;
use App\Models\Phone;
use App\Support\Slugs;
use App\Support\SocialLinks;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PUT /admin/site-info: the whole restaurant info (frontend saveSiteInfo,
 * checkSiteInfoInput; 422 keys as API-CONTRACT §4 Site info). Phones and
 * branches are saved by id (§4.1): an id must be one of ours, `id: ""` is a
 * new one. The 409 rules (orders left without WhatsApp) are in SaveSiteInfo.
 */
class UpdateSiteInfoRequest extends FormRequest
{
    /** The frontend's SITE_INFO_LIMITS (+ server-side guards, decision 10.3). */
    public const LIMITS = [
        'tagline' => 120,
        'taglineLines' => 3,
        'phones' => 10,
        'branches' => 6,
        'branchName' => 30,
        'branchAddress' => 80,
        'mapUrl' => 500,
        'openingHours' => 80,
    ];

    /** `+963` and 8 or 9 digits: the form the dashboard saves (frontend normalizePhone). */
    public const PHONE_FORMAT = '/^\+963\d{8,9}$/';

    public const PHONE_NOT_OURS = 'هالرقم مش موجود، حدّث الصفحة';

    public const BRANCH_NOT_OURS = 'هالفرع مش موجود، حدّث الصفحة';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request. The checks across
     * items (ids, duplicates, counts, hours) are in after().
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tagline' => ['required', 'string', 'max:'.self::LIMITS['tagline']],
            'phones' => ['present', 'array'],
            'phones.*' => ['array'],
            'phones.*.id' => ['nullable'],
            'phones.*.number' => ['required', 'string', 'regex:'.self::PHONE_FORMAT],
            'phones.*.hasWhatsApp' => ['required', 'boolean'],
            'phones.*.branchId' => ['nullable', 'string'],
            'branches' => ['present', 'array'],
            'branches.*' => ['array'],
            'branches.*.id' => ['nullable'],
            'branches.*.slug' => ['nullable'],
            'branches.*.name' => ['required', 'string', 'max:'.self::LIMITS['branchName']],
            'branches.*.address' => ['required', 'string', 'max:'.self::LIMITS['branchAddress']],
            'branches.*.mapUrl' => ['nullable', 'string', 'max:'.self::LIMITS['mapUrl'], 'regex:/^https:\/\/\S+$/'],
            'openingHours' => ['nullable', 'string', 'max:'.self::LIMITS['openingHours']],
            'openingHoursSchema' => ['nullable', 'array'],
            'openingHoursSchema.*' => ['string'],
            'socialLinks' => ['nullable', 'array'],
            'socialLinks.*' => ['array'],
            'socialLinks.*.platform' => ['required', Rule::enum(SocialPlatform::class)],
            'socialLinks.*.url' => ['required', 'string'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mapUrl = 'انسخ الرابط من Google Maps (زر «مشاركة»)';

        return [
            'tagline.required' => 'اكتب جملة تعريف قصيرة',
            'tagline.string' => 'اكتب جملة تعريف قصيرة',
            'tagline.max' => 'التعريف '.self::LIMITS['tagline'].' حرف أو أقل',
            'phones.*.number.required' => 'الرقم مش مفهوم. اكتبه متل 0930 123 456',
            'phones.*.number.string' => 'الرقم مش مفهوم. اكتبه متل 0930 123 456',
            'phones.*.number.regex' => 'الرقم مش مفهوم. اكتبه متل 0930 123 456',
            'branches.*.name.required' => 'اكتب اسم الفرع',
            'branches.*.name.string' => 'اكتب اسم الفرع',
            'branches.*.name.max' => self::LIMITS['branchName'].' حرف أو أقل',
            'branches.*.address.required' => 'اكتب العنوان',
            'branches.*.address.string' => 'اكتب العنوان',
            'branches.*.address.max' => self::LIMITS['branchAddress'].' حرف أو أقل',
            'branches.*.mapUrl.string' => $mapUrl,
            'branches.*.mapUrl.max' => $mapUrl,
            'branches.*.mapUrl.regex' => $mapUrl,
            'openingHours.string' => 'اختار ساعة الفتح وساعة السكّر، أو فضّي التنين',
            'openingHours.max' => self::LIMITS['openingHours'].' حرف أو أقل',
            'socialLinks.*.platform.required' => 'اختار المنصة',
            'socialLinks.*.platform.enum' => 'اختار المنصة',
        ];
    }

    /**
     * One kind of line break, so the three-line rule and the stored text agree.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('tagline'))) {
            $this->merge(['tagline' => str_replace(["\r\n", "\r"], "\n", $this->input('tagline'))]);
        }
    }

    /**
     * The checks across fields, with the frontend's keys and texts.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $data = $validator->getData();
                $errors = $validator->errors();

                if (is_string($data['tagline'] ?? null) && ! $errors->has('tagline')
                    && count(explode("\n", $data['tagline'])) > self::LIMITS['taglineLines']) {
                    $errors->add('tagline', self::LIMITS['taglineLines'].' أسطر بالكتير');
                }

                if (is_array($data['phones'] ?? null)) {
                    $this->checkPhones($validator, array_values($data['phones']));
                }

                if (is_array($data['branches'] ?? null)) {
                    $this->checkBranches($validator, array_values($data['branches']));
                }

                $this->checkOpeningHours($validator, $data['openingHours'] ?? null, $data['openingHoursSchema'] ?? null);

                if (is_array($data['socialLinks'] ?? null)) {
                    $this->checkSocialLinks($validator, array_values($data['socialLinks']));
                }
            },
        ];
    }

    /**
     * At least one restaurant phone, at most 10 in all; every id one of ours;
     * every branch one of ours; no number twice for the same owner.
     *
     * @param  array<int, mixed>  $phones
     */
    protected function checkPhones(Validator $validator, array $phones): void
    {
        $errors = $validator->errors();
        $phones = array_map(fn ($phone) => is_array($phone) ? $phone : [], $phones);

        if (! collect($phones)->contains(fn (array $phone) => blank($phone['branchId'] ?? null))) {
            $errors->add('phones', 'ضيف رقم واحد للمطعم على الأقل، ليقدر الزبون يتواصل معكن');
        } elseif (count($phones) > self::LIMITS['phones']) {
            $errors->add('phones', 'أقصى شي '.self::LIMITS['phones'].' أرقام');
        }

        $ourIds = Phone::pluck('id')->map(fn (int $id) => (string) $id)->all();
        $ourBranches = Branch::pluck('slug')->all();
        $seenIds = [];
        $seenNumbers = [];

        foreach ($phones as $index => $phone) {
            $id = $phone['id'] ?? null;
            if (filled($id)) {
                if (! in_array((string) $id, $ourIds, true) || in_array((string) $id, $seenIds, true)) {
                    $errors->add("phones.{$index}.id", self::PHONE_NOT_OURS);
                }
                $seenIds[] = (string) $id;
            }

            $branchId = $phone['branchId'] ?? null;
            if (filled($branchId) && ! in_array($branchId, $ourBranches, true)) {
                $errors->add("phones.{$index}.branchId", self::BRANCH_NOT_OURS);
            }

            if ($errors->has("phones.{$index}.number")) {
                continue;
            }

            // The same number may serve the restaurant and a branch, but not twice in one of them.
            $key = ($branchId ?? '').'|'.$phone['number'];
            if (in_array($key, $seenNumbers, true)) {
                $errors->add("phones.{$index}.number", filled($branchId) ? 'هالرقم مكتوب مرتين لنفس الفرع' : 'هالرقم مكتوب مرتين');
            }
            $seenNumbers[] = $key;
        }
    }

    /**
     * One to six branches; a saved branch by its id (slug), a new one may
     * bring its «رابط» (a saved branch's link never changes: it's printed in
     * the table QR codes).
     *
     * @param  array<int, mixed>  $branches
     */
    protected function checkBranches(Validator $validator, array $branches): void
    {
        $errors = $validator->errors();

        if ($branches === []) {
            $errors->add('branches', 'ضيف فرع واحد على الأقل');
        } elseif (count($branches) > self::LIMITS['branches']) {
            $errors->add('branches', 'أقصى شي '.self::LIMITS['branches'].' فروع');
        }

        $ourBranches = Branch::pluck('slug')->all();
        $seenIds = [];
        $newSlugs = [];

        foreach ($branches as $index => $branch) {
            $branch = is_array($branch) ? $branch : [];
            $id = $branch['id'] ?? null;

            if (filled($id)) {
                if (! in_array($id, $ourBranches, true) || in_array($id, $seenIds, true)) {
                    $errors->add("branches.{$index}.id", self::BRANCH_NOT_OURS);
                }
                $seenIds[] = $id;

                continue;
            }

            $slug = $branch['slug'] ?? null;
            if (blank($slug)) {
                continue;
            }

            $slugCheck = ValidatorFactory::make(['slug' => $slug], ['slug' => Slugs::rules(['branches'])], Slugs::messages());
            if ($slugCheck->fails()) {
                $errors->add("branches.{$index}.slug", $slugCheck->errors()->first('slug'));
            } elseif (in_array($slug, $newSlugs, true)) {
                $errors->add("branches.{$index}.slug", Slugs::messages()['slug.unique']);
            }
            $newSlugs[] = $slug;
        }
    }

    /**
     * The hours text and its schema.org value come from the same two clock
     * times: both or neither (frontend parseHoursSchema).
     */
    protected function checkOpeningHours(Validator $validator, mixed $text, mixed $schema): void
    {
        if ($validator->errors()->has('openingHours')) {
            return;
        }

        $hasSchema = is_array($schema) && count($schema) === 1
            && is_string($schema[0] ?? null) && preg_match('/^Mo-Su \d{2}:\d{2}-\d{2}:\d{2}$/', $schema[0]);

        if (filled($text) !== (bool) $hasSchema) {
            $validator->errors()->add('openingHours', 'اختار ساعة الفتح وساعة السكّر، أو فضّي التنين');
        }
    }

    /**
     * Each link a page on its platform, in the normalized form; one per platform.
     *
     * @param  array<int, mixed>  $links
     */
    protected function checkSocialLinks(Validator $validator, array $links): void
    {
        $errors = $validator->errors();
        $seen = [];

        foreach ($links as $index => $link) {
            $platform = SocialPlatform::tryFrom(is_array($link) && is_string($link['platform'] ?? null) ? $link['platform'] : '');
            if ($platform === null) {
                continue;
            }

            if (in_array($platform, $seen, true)) {
                $errors->add("socialLinks.{$index}.platform", 'في رابط تاني لنفس المنصة');
            }
            $seen[] = $platform;

            $url = $link['url'] ?? null;
            if (is_string($url) && ! SocialLinks::isNormalized($platform, $url)) {
                $errors->add("socialLinks.{$index}.url", "هالرابط مش لصفحة على {$platform->label()}");
            }
        }
    }
}
