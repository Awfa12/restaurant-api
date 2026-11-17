<?php

use App\Models\Branch;
use App\Models\Phone;

/*
 * Dashboard ordering settings (API-CONTRACT §4 Ordering, decisions 11.2 and
 * 11.3; rules and texts from the frontend's checkOrderingInput).
 */

/**
 * What the dashboard sends when it saves: the admin GET it loaded, changed.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>|null  $change
 * @return array<string, mixed>
 */
function orderingInput(?Closure $change = null): array
{
    $input = test()->getJson('/api/v1/admin/ordering')->json();

    return $change ? $change($input) : $input;
}

it('returns 401 without a token', function (string $method) {
    $this->json($method, '/api/v1/admin/ordering', [])->assertUnauthorized();
})->with(['GET', 'PUT']);

describe('show', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->restaurant = seedRestaurant();
    });

    it('returns the settings with every branch, also one never set up', function () {
        $this->restaurant['qanawat']->update(['dine_in_enabled' => true, 'table_count' => 10]);

        $response = $this->getJson('/api/v1/admin/ordering');

        $response->assertOk()->assertExactJson([
            'delivery' => ['isEnabled' => true, 'fee' => 150, 'phoneId' => (string) $this->restaurant['main']->id],
            'dineIn' => ['isEnabled' => false, 'branches' => [
                ['branchId' => 'qanawat-road', 'isEnabled' => true, 'tableCount' => 10],
                ['branchId' => 'al-mehwari', 'isEnabled' => false, 'tableCount' => 0],
            ]],
            'message' => ['greeting' => 'مرحبا {restaurant}، بدي اطلب:', 'closing' => 'شكراً إلكن'],
            'thankYou' => 'شكراً {name}!',
        ]);
    });

    it('sends a delivery phone that no longer exists as an empty id', function () {
        $this->restaurant['ordering']->update(['delivery_enabled' => false]);
        $this->restaurant['main']->delete();

        $this->getJson('/api/v1/admin/ordering')->assertOk()->assertJsonPath('delivery.phoneId', '');
    });
});

describe('update', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->restaurant = seedRestaurant();
    });

    it('saves back what it loaded without changing anything', function () {
        $before = orderingInput();

        $this->putJson('/api/v1/admin/ordering', $before)->assertOk()->assertExactJson($before);
    });

    it('saves the whole settings: delivery, tables per branch and the texts', function () {
        $input = orderingInput(function (array $input) {
            $input['delivery'] = ['isEnabled' => true, 'fee' => 200, 'phoneId' => (string) $this->restaurant['second']->id];
            $input['dineIn'] = ['isEnabled' => true, 'branches' => [
                ['branchId' => 'qanawat-road', 'isEnabled' => true, 'tableCount' => 12],
                ['branchId' => 'al-mehwari', 'isEnabled' => false, 'tableCount' => 4],
            ]];
            $input['message'] = ['greeting' => 'أهلين {restaurant}', 'closing' => ''];
            $input['thankYou'] = 'تسلم {name}';

            return $input;
        });

        $response = $this->putJson('/api/v1/admin/ordering', $input);

        $response->assertOk()->assertExactJson($input);
        expect($this->restaurant['mehwari']->refresh())->dine_in_enabled->toBeFalse()->table_count->toBe(4)
            ->and($this->restaurant['ordering']->refresh()->closing)->toBe('');
    });

    it('switches off a branch it leaves out and keeps its table count', function () {
        $this->restaurant['mehwari']->update(['dine_in_enabled' => true, 'table_count' => 8]);
        $input = orderingInput(function (array $input) {
            array_pop($input['dineIn']['branches']);

            return $input;
        });

        $this->putJson('/api/v1/admin/ordering', $input)->assertOk();

        expect($this->restaurant['mehwari']->refresh())->dine_in_enabled->toBeFalse()->table_count->toBe(8);
    });

    it('keeps the delivery phone while delivery is off only if it is a restaurant phone', function (Closure $phoneId, bool $kept) {
        $input = orderingInput(function (array $input) use ($phoneId) {
            $input['delivery'] = ['isEnabled' => false, 'fee' => 0, 'phoneId' => $phoneId($this->restaurant)];

            return $input;
        });

        $this->putJson('/api/v1/admin/ordering', $input)->assertOk();

        expect($this->restaurant['ordering']->refresh()->delivery_phone_id)
            ->toBe($kept ? $this->restaurant['second']->id : null);
    })->with([
        'a restaurant phone' => [fn (array $restaurant) => (string) $restaurant['second']->id, true],
        'a branch phone' => [fn (array $restaurant) => (string) $restaurant['qanawatStaff']->id, false],
        'none' => [fn (array $restaurant) => '', false],
    ]);

    it('never touches the branch names, addresses or phones', function () {
        $before = Branch::orderBy('id')->get(['name', 'address'])->toArray();
        $phones = Phone::count();

        $this->putJson('/api/v1/admin/ordering', orderingInput())->assertOk();

        expect(Branch::orderBy('id')->get(['name', 'address'])->toArray())->toBe($before)
            ->and(Phone::count())->toBe($phones);
    });
});

describe('update: validation (422)', function () {
    beforeEach(function () {
        actingAsAdmin();
        $this->restaurant = seedRestaurant();
    });

    it('returns the frontend messages on the contract keys', function (Closure $change, string $field, string $message) {
        $response = $this->putJson('/api/v1/admin/ordering', orderingInput(fn (array $input) => $change($input, $this->restaurant)));

        $response->assertUnprocessable();
        expect($response->json('errors')[$field] ?? [])->toContain($message);
    })->with([
        'fee not a number' => [fn (array $i) => tap($i, function (array &$i) {
            $i['delivery']['fee'] = 'كتير';
        }), 'delivery.fee', 'اكتب رسوم التوصيل رقم، متل 150 (أو 0 إذا مجاني)'],
        'fee below zero' => [fn (array $i) => tap($i, function (array &$i) {
            $i['delivery']['fee'] = -1;
        }), 'delivery.fee', 'اكتب رسوم التوصيل رقم، متل 150 (أو 0 إذا مجاني)'],
        'fee too big' => [fn (array $i) => tap($i, function (array &$i) {
            $i['delivery']['fee'] = 10_000_001;
        }), 'delivery.fee', 'الرسوم كبيرة كتير، تأكد منها'],
        'delivery on to a branch phone' => [fn (array $i, array $r) => tap($i, function (array &$i) use ($r) {
            $i['delivery']['phoneId'] = (string) $r['qanawatStaff']->id;
        }), 'delivery.phoneId', 'اختار رقم عليه واتساب لتوصل عليه طلبات التوصيل'],
        'delivery on to a phone without WhatsApp' => [fn (array $i, array $r) => tap($i, function (array &$i) use ($r) {
            $r['second']->update(['has_whatsapp' => false]);
            $i['delivery']['phoneId'] = (string) $r['second']->id;
        }), 'delivery.phoneId', 'اختار رقم عليه واتساب لتوصل عليه طلبات التوصيل'],
        'delivery on, no phone' => [fn (array $i) => tap($i, function (array &$i) {
            $i['delivery']['phoneId'] = '';
        }), 'delivery.phoneId', 'اختار رقم عليه واتساب لتوصل عليه طلبات التوصيل'],
        'an unknown branch' => [fn (array $i) => tap($i, function (array &$i) {
            $i['dineIn']['branches'][] = ['branchId' => 'nowhere', 'isEnabled' => false, 'tableCount' => 0];
        }), 'dineIn', 'في فرع مش موجود، حدّث الصفحة'],
        'a branch twice' => [fn (array $i) => tap($i, function (array &$i) {
            $i['dineIn']['branches'][] = $i['dineIn']['branches'][0];
        }), 'dineIn', 'في فرع مش موجود، حدّث الصفحة'],
        'too many tables' => [fn (array $i) => tap($i, function (array &$i) {
            $i['dineIn']['branches'][0]['tableCount'] = 100;
        }), 'dineIn.branches.0.tableCount', 'عدد الطاولات من 1 لـ 99'],
        'a branch on with no tables' => [fn (array $i) => tap($i, function (array &$i) {
            $i['dineIn']['branches'][1] = ['branchId' => 'al-mehwari', 'isEnabled' => true, 'tableCount' => 0];
        }), 'dineIn.branches.1.tableCount', 'اكتب كم طاولة بالفرع'],
        'a branch on without a WhatsApp phone' => [fn (array $i, array $r) => tap($i, function (array &$i) use ($r) {
            $r['mehwariStaff']->update(['has_whatsapp' => false]);
            $i['dineIn']['branches'][1] = ['branchId' => 'al-mehwari', 'isEnabled' => true, 'tableCount' => 5];
        }), 'dineIn.branches.1.isEnabled', 'هالفرع ما إلو رقم واتساب. ضيفه من «معلومات المطعم» أول'],
        'table orders on, no branch on' => [fn (array $i) => tap($i, function (array &$i) {
            $i['dineIn']['isEnabled'] = true;
        }), 'dineIn', 'شغّل طاولات فرع واحد على الأقل، أو طفّي طلبات الطاولات'],
        'no greeting' => [fn (array $i) => tap($i, function (array &$i) {
            $i['message']['greeting'] = ' ';
        }), 'message.greeting', 'هالنص ما بيصير يكون فاضي'],
        'greeting too long' => [fn (array $i) => tap($i, function (array &$i) {
            $i['message']['greeting'] = str_repeat('ع', 121);
        }), 'message.greeting', '120 حرف أو أقل'],
        'closing too long' => [fn (array $i) => tap($i, function (array &$i) {
            $i['message']['closing'] = str_repeat('ع', 201);
        }), 'message.closing', '200 حرف أو أقل'],
        'no thank-you' => [fn (array $i) => tap($i, function (array &$i) {
            $i['thankYou'] = '';
        }), 'thankYou', 'هالنص ما بيصير يكون فاضي'],
        'an unknown placeholder' => [fn (array $i) => tap($i, function (array &$i) {
            $i['thankYou'] = 'شكراً {customer}';
        }), 'thankYou', 'استعمل زرّي «اسم المطعم» و«اسم الزبون» بدل ما تكتب أقواس { }'],
    ]);
});
