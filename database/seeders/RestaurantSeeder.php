<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\OrderingSetting;
use App\Models\Phone;
use App\Models\Setting;
use Database\Seeders\Support\SeedData;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Site info (settings + branches + phones) and the ordering settings, from
 * site-info.json and ordering.json (frontend src/data/mock/site-info.ts,
 * ordering.ts). Together because ordering points at phones and branches.
 *
 * Branch slugs are kept (printed in the table QR codes). Phones get new ids:
 * `delivery.phoneId` and every `phone.branchId` / `dineIn.branchId` are
 * remapped. Called by DatabaseSeeder only.
 */
class RestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $siteInfo = SeedData::read('site-info');
        $ordering = SeedData::read('ordering');

        Setting::create(['key' => 'site_info', 'value' => array_filter([
            'tagline' => $siteInfo['tagline'],
            'openingHours' => $siteInfo['openingHours'] ?? null,
            'openingHoursSchema' => $siteInfo['openingHoursSchema'] ?? null,
            'socialLinks' => $siteInfo['socialLinks'] ?? null,
        ], fn ($value) => $value !== null)]);

        // The dine-in settings are branch columns (decision 1.1).
        $dineIn = collect($ordering['dineIn']['branches'])->keyBy('branchId');
        $unknown = $dineIn->keys()->diff(collect($siteInfo['branches'])->pluck('id'));
        if ($unknown->isNotEmpty()) {
            throw new RuntimeException('ordering.dineIn: unknown branch '.$unknown->first().'.');
        }

        foreach ($siteInfo['branches'] as $index => $branch) {
            Branch::create([
                'slug' => $branch['id'],
                'name' => $branch['name'],
                'address' => $branch['address'],
                'map_url' => $branch['mapUrl'] ?? null,
                'dine_in_enabled' => $dineIn[$branch['id']]['isEnabled'] ?? false,
                'table_count' => $dineIn[$branch['id']]['tableCount'] ?? 0,
                'position' => $index + 1,
            ]);
        }

        $branchIds = Branch::pluck('id', 'slug');
        $phoneIds = [];

        foreach ($siteInfo['phones'] as $index => $phone) {
            $branchSlug = $phone['branchId'] ?? null;

            $phoneIds[$phone['id']] = Phone::create([
                'number' => $phone['number'],
                'has_whatsapp' => $phone['hasWhatsApp'],
                'branch_id' => $branchSlug === null ? null : ($branchIds[$branchSlug] ?? throw new RuntimeException("Phone {$phone['id']}: unknown branch {$branchSlug}.")),
                'position' => $index + 1,
            ])->id;
        }

        OrderingSetting::create([
            'delivery_enabled' => $ordering['delivery']['isEnabled'],
            'delivery_fee' => $ordering['delivery']['fee'],
            'delivery_phone_id' => $phoneIds[$ordering['delivery']['phoneId']] ?? throw new RuntimeException("ordering.delivery: unknown phone {$ordering['delivery']['phoneId']}."),
            'dine_in_enabled' => $ordering['dineIn']['isEnabled'],
            'greeting' => $ordering['message']['greeting'],
            'closing' => $ordering['message']['closing'],
            'thank_you' => $ordering['thankYou'],
        ]);
    }
}
