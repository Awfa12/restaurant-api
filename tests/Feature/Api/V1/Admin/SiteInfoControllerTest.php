<?php

use App\Models\Branch;
use App\Models\Phone;
use App\Models\Setting;

/*
 * Dashboard restaurant info (API-CONTRACT §4 Site info, §4.1 saved by id;
 * decisions 10.1–10.3; rules and texts from the frontend's checkSiteInfoInput
 * and orderingConflicts).
 */

/**
 * What the dashboard sends when it saves: the admin GET it loaded, changed.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>|null  $change
 * @return array<string, mixed>
 */
function siteInfoInput(?Closure $change = null): array
{
    $input = test()->getJson('/api/v1/admin/site-info')->json();

    return $change ? $change($input) : $input;
}

/**
 * A 422's messages for one key (keys with dots read directly: assertJsonPath nests them).
 *
 * @return array<int, string>
 */
function siteInfoErrors(array $input, string $field): array
{
    $response = test()->putJson('/api/v1/admin/site-info', $input);
    $response->assertUnprocessable();

    return $response->json('errors')[$field] ?? [];
}

it('returns 401 without a token', function (string $method) {
    $this->json($method, '/api/v1/admin/site-info', [])->assertUnauthorized();
})->with(['GET', 'PUT']);

describe('show', function () {
    it('returns everything, the staff phones with their branch slug', function () {
        actingAsAdmin();
        $restaurant = seedRestaurant();

        $response = $this->getJson('/api/v1/admin/site-info');

        $response->assertOk()->assertJsonCount(4, 'phones')->assertJsonPath('phones.2', [
            'id' => (string) $restaurant['qanawatStaff']->id,
            'number' => '+963930000001',
            'hasWhatsApp' => true,
            'branchId' => 'qanawat-road',
        ])->assertJsonMissingPath('phones.0.branchId');
    });
});

describe('update: saved by id', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->restaurant = seedRestaurant();
    });

    it('saves back what it loaded without changing anything', function () {
        $before = siteInfoInput();

        $response = $this->putJson('/api/v1/admin/site-info', $before);

        $response->assertOk()->assertExactJson($before);
    });

    it('updates a phone in place (same id), creates the new one and deletes the missing one', function () {
        $input = siteInfoInput(function (array $input) {
            $input['phones'][1]['number'] = '+963999999999';
            unset($input['phones'][0]);
            $input['phones'][] = ['id' => '', 'number' => '+963911111111', 'hasWhatsApp' => false];

            return $input;
        });
        $input['phones'] = array_values($input['phones']);
        // Delivery is on to the first number: point it elsewhere first, as the dashboard would
        $this->restaurant['ordering']->update(['delivery_enabled' => false]);

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk();
        expect($response->json('phones.0'))->toBe(['id' => (string) $this->restaurant['second']->id, 'number' => '+963999999999', 'hasWhatsApp' => true])
            ->and($response->json('phones.3.number'))->toBe('+963911111111')
            ->and(Phone::find($this->restaurant['main']->id))->toBeNull();
    });

    it('keeps the order sent, which decides the branch phone that gets the table orders', function () {
        $input = siteInfoInput(function (array $input) {
            $input['phones'][] = ['id' => '', 'number' => '+963922222222', 'hasWhatsApp' => true, 'branchId' => 'qanawat-road'];
            $input['phones'] = [$input['phones'][4], ...array_slice($input['phones'], 0, 4)];

            return $input;
        });

        $this->putJson('/api/v1/admin/site-info', $input)->assertOk();

        expect($this->restaurant['qanawat']->phones()->pluck('number')->all())->toBe(['+963922222222', '+963930000001']);
    });

    it('renames a branch and keeps its slug, even when a «رابط» is sent for it', function () {
        $input = siteInfoInput(function (array $input) {
            $input['branches'][0]['name'] = 'فرع قنوات الجديد';
            $input['branches'][0]['slug'] = 'renamed';

            return $input;
        });

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk()->assertJsonPath('branches.0', ['id' => 'qanawat-road', 'name' => 'فرع قنوات الجديد', 'address' => 'السويداء، طريق قنوات']);
    });

    it('creates a new branch with its «رابط», or a neutral slug, and no tables', function (?string $slug, string $pattern) {
        $input = siteInfoInput(function (array $input) use ($slug) {
            $input['branches'][] = array_filter(['id' => '', 'slug' => $slug, 'name' => 'فرع جديد', 'address' => 'السويداء، الساحة'], fn ($value) => $value !== null);

            return $input;
        });

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk();
        expect($response->json('branches.2.id'))->toMatch($pattern);
        $this->assertDatabaseHas('branches', ['slug' => $response->json('branches.2.id'), 'dine_in_enabled' => false, 'table_count' => 0, 'position' => 3]);
    })->with([
        'with a «رابط»' => ['al-saha', '/^al-saha$/'],
        'without' => [null, '/^branch-[a-z0-9]{8}$/'],
    ]);

    it('deletes a missing branch with its staff phones and table settings', function () {
        $this->restaurant['mehwari']->update(['dine_in_enabled' => true, 'table_count' => 8]);
        $input = siteInfoInput(function (array $input) {
            unset($input['branches'][1]);
            $input['branches'] = array_values($input['branches']);

            return $input;
        });

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk()->assertJsonCount(1, 'branches')->assertJsonCount(3, 'phones');
        expect(Branch::where('slug', 'al-mehwari')->exists())->toBeFalse()
            ->and(Phone::find($this->restaurant['mehwariStaff']->id))->toBeNull();
    });

    it('keeps a staff phone moved to the restaurant in the same save that deletes its branch', function () {
        $input = siteInfoInput(function (array $input) {
            unset($input['branches'][1], $input['phones'][3]['branchId']);
            $input['branches'] = array_values($input['branches']);

            return $input;
        });

        $this->putJson('/api/v1/admin/site-info', $input)->assertOk();

        expect(Phone::find($this->restaurant['mehwariStaff']->id))
            ->not->toBeNull()
            ->branch_id->toBeNull();
    });

    it('saves the texts, hours and social links', function () {
        $input = siteInfoInput(function (array $input) {
            $input['tagline'] = 'جملة جديدة';
            $input['openingHours'] = 'يومياً من 10 الصبح لـ 11 بالليل';
            $input['openingHoursSchema'] = ['Mo-Su 10:00-23:00'];
            $input['socialLinks'] = [['platform' => 'instagram', 'url' => 'https://instagram.com/ahlalkaram']];

            return $input;
        });

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk()->assertJsonPath('socialLinks', [['platform' => 'instagram', 'url' => 'https://instagram.com/ahlalkaram']]);
        expect(Setting::where('key', 'site_info')->first()->value)->toMatchArray(['tagline' => 'جملة جديدة', 'openingHoursSchema' => ['Mo-Su 10:00-23:00']]);
    });

    it('clears the hours and links it does not send', function () {
        $input = siteInfoInput();
        unset($input['openingHours'], $input['openingHoursSchema']);

        $response = $this->putJson('/api/v1/admin/site-info', $input);

        $response->assertOk()->assertJsonMissingPath('openingHours')->assertJsonMissingPath('openingHoursSchema')->assertJsonMissingPath('socialLinks');
    });
});

describe('update: orders must keep a WhatsApp number (409)', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->restaurant = seedRestaurant();
    });

    it('refuses to remove the delivery phone, or its WhatsApp, while delivery is on', function (Closure $change) {
        $before = Phone::count();

        $response = $this->putJson('/api/v1/admin/site-info', siteInfoInput($change));

        $response->assertConflict()->assertExactJson(['message' => 'طلبات التوصيل بدها رقم عليه واتساب: خلّي رقم التوصيل، أو غيّره بدل ما تحذفه.']);
        expect(Phone::count())->toBe($before);
    })->with([
        'removed' => function (array $input) {
            array_shift($input['phones']);

            return $input;
        },
        'WhatsApp turned off' => function (array $input) {
            $input['phones'][0]['hasWhatsApp'] = false;

            return $input;
        },
    ]);

    it('refuses to leave a branch taking table orders without a WhatsApp phone of its own, and says which', function () {
        $this->restaurant['ordering']->update(['dine_in_enabled' => true]);
        $this->restaurant['mehwari']->update(['dine_in_enabled' => true, 'table_count' => 8]);

        $response = $this->putJson('/api/v1/admin/site-info', siteInfoInput(function (array $input) {
            $input['phones'][3]['hasWhatsApp'] = false;

            return $input;
        }));

        $response->assertConflict()->assertExactJson(['message' => 'طاولات فرع المحوري بدها رقم واتساب تابع للفرع.']);
    });

    it('allows the same change while those orders are off', function () {
        $this->restaurant['ordering']->update(['delivery_enabled' => false]);

        $response = $this->putJson('/api/v1/admin/site-info', siteInfoInput(function (array $input) {
            array_shift($input['phones']);

            return $input;
        }));

        $response->assertOk();
        expect($this->restaurant['ordering']->refresh()->delivery_phone_id)->toBeNull();
    });
});

describe('update: validation (422)', function () {
    beforeEach(function () {
        actingAsAdmin();
        seedRestaurant();
    });

    it('returns the frontend messages on the contract keys', function (Closure $change, string $field, string $message) {
        expect(siteInfoErrors(siteInfoInput($change), $field))->toContain($message);
    })->with([
        'no tagline' => [fn (array $i) => [...$i, 'tagline' => ''], 'tagline', 'اكتب جملة تعريف قصيرة'],
        'tagline too long' => [fn (array $i) => [...$i, 'tagline' => str_repeat('ع', 121)], 'tagline', 'التعريف 120 حرف أو أقل'],
        'tagline of four lines' => [fn (array $i) => [...$i, 'tagline' => "١\n٢\n٣\n٤"], 'tagline', '3 أسطر بالكتير'],
        'no restaurant phone' => [fn (array $i) => [...$i, 'phones' => array_slice($i['phones'], 2)], 'phones', 'ضيف رقم واحد للمطعم على الأقل، ليقدر الزبون يتواصل معكن'],
        'eleven phones' => [fn (array $i) => [...$i, 'phones' => [...$i['phones'], ...array_map(fn (int $n) => ['id' => '', 'number' => '+96393000010'.$n, 'hasWhatsApp' => false], range(0, 6))]], 'phones', 'أقصى شي 10 أرقام'],
        'a number not in the saved form' => [fn (array $i) => tap($i, function (array &$i) {
            $i['phones'][1]['number'] = '0930 123 456';
        }), 'phones.1.number', 'الرقم مش مفهوم. اكتبه متل 0930 123 456'],
        'the same restaurant number twice' => [fn (array $i) => [...$i, 'phones' => [...$i['phones'], ['id' => '', 'number' => '+963930539614', 'hasWhatsApp' => true]]], 'phones.4.number', 'هالرقم مكتوب مرتين'],
        'the same branch number twice' => [fn (array $i) => [...$i, 'phones' => [...$i['phones'], ['id' => '', 'number' => '+963930000001', 'hasWhatsApp' => true, 'branchId' => 'qanawat-road']]], 'phones.4.number', 'هالرقم مكتوب مرتين لنفس الفرع'],
        'a phone id that is not ours' => [fn (array $i) => [...$i, 'phones' => [...$i['phones'], ['id' => '999', 'number' => '+963911111111', 'hasWhatsApp' => true]]], 'phones.4.id', 'هالرقم مش موجود، حدّث الصفحة'],
        'a phone of an unknown branch' => [fn (array $i) => [...$i, 'phones' => [...$i['phones'], ['id' => '', 'number' => '+963911111111', 'hasWhatsApp' => true, 'branchId' => 'nowhere']]], 'phones.4.branchId', 'هالفرع مش موجود، حدّث الصفحة'],
        'no branches' => [fn (array $i) => [...$i, 'branches' => [], 'phones' => array_slice($i['phones'], 0, 2)], 'branches', 'ضيف فرع واحد على الأقل'],
        'seven branches' => [fn (array $i) => [...$i, 'branches' => [...$i['branches'], ...array_map(fn (int $n) => ['id' => '', 'name' => "فرع {$n}", 'address' => 'عنوان'], range(1, 5))]], 'branches', 'أقصى شي 6 فروع'],
        'a branch id that is not ours' => [fn (array $i) => [...$i, 'branches' => [...$i['branches'], ['id' => 'nowhere', 'name' => 'فرع', 'address' => 'عنوان']]], 'branches.2.id', 'هالفرع مش موجود، حدّث الصفحة'],
        'no branch name' => [fn (array $i) => tap($i, function (array &$i) {
            $i['branches'][0]['name'] = ' ';
        }), 'branches.0.name', 'اكتب اسم الفرع'],
        'branch address too long' => [fn (array $i) => tap($i, function (array &$i) {
            $i['branches'][1]['address'] = str_repeat('ع', 81);
        }), 'branches.1.address', '80 حرف أو أقل'],
        'a map link that is not https' => [fn (array $i) => tap($i, function (array &$i) {
            $i['branches'][0]['mapUrl'] = 'http://maps.example/x';
        }), 'branches.0.mapUrl', 'انسخ الرابط من Google Maps (زر «مشاركة»)'],
        'a new branch with a taken «رابط»' => [fn (array $i) => [...$i, 'branches' => [...$i['branches'], ['id' => '', 'slug' => 'al-mehwari', 'name' => 'فرع', 'address' => 'عنوان']]], 'branches.2.slug', 'هالرابط مستعمل، اختار غيره'],
        'two new branches with the same «رابط»' => [fn (array $i) => [...$i, 'branches' => [...$i['branches'], ['id' => '', 'slug' => 'al-saha', 'name' => 'فرع', 'address' => 'عنوان'], ['id' => '', 'slug' => 'al-saha', 'name' => 'فرع ٢', 'address' => 'عنوان']]], 'branches.3.slug', 'هالرابط مستعمل، اختار غيره'],
        'hours text without its schema' => [fn (array $i) => tap($i, function (array &$i) {
            unset($i['openingHoursSchema']);
        }), 'openingHours', 'اختار ساعة الفتح وساعة السكّر، أو فضّي التنين'],
        'hours text too long' => [fn (array $i) => [...$i, 'openingHours' => str_repeat('ع', 81)], 'openingHours', '80 حرف أو أقل'],
        'a link to another platform' => [fn (array $i) => [...$i, 'socialLinks' => [['platform' => 'instagram', 'url' => 'https://facebook.com/x']]], 'socialLinks.0.url', 'هالرابط مش لصفحة على Instagram'],
        'a link not in its saved form' => [fn (array $i) => [...$i, 'socialLinks' => [['platform' => 'tiktok', 'url' => 'tiktok.com/@x']]], 'socialLinks.0.url', 'هالرابط مش لصفحة على TikTok'],
        'two links for one platform' => [fn (array $i) => [...$i, 'socialLinks' => [['platform' => 'instagram', 'url' => 'https://instagram.com/a'], ['platform' => 'instagram', 'url' => 'https://instagram.com/b']]], 'socialLinks.1.platform', 'في رابط تاني لنفس المنصة'],
        'an unknown platform' => [fn (array $i) => [...$i, 'socialLinks' => [['platform' => 'myspace', 'url' => 'https://myspace.com/x']]], 'socialLinks.0.platform', 'اختار المنصة'],
    ]);
});
