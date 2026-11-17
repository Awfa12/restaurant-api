<?php

namespace App\Actions;

use App\Http\Requests\Api\V1\Admin\UpdateOrderingRequest;
use App\Models\Branch;
use App\Models\OrderingSetting;
use Illuminate\Support\Facades\DB;

/**
 * PUT /admin/ordering: the whole ordering settings, in one transaction
 * (API-CONTRACT §4 Ordering). The per-branch table settings are branch
 * columns (decision 1.1); this save touches only those columns, never the
 * branch's name, address or phones.
 */
class SaveOrdering
{
    /**
     * @param  array<string, mixed>  $input  UpdateOrderingRequest::validated()
     */
    public function save(array $input): void
    {
        DB::transaction(function () use ($input) {
            $phoneId = $input['delivery']['phoneId'] ?? null;

            $ordering = OrderingSetting::query()->lockForUpdate()->first() ?? new OrderingSetting;
            $ordering->fill([
                'delivery_enabled' => (bool) $input['delivery']['isEnabled'],
                'delivery_fee' => (int) $input['delivery']['fee'],
                // Kept while delivery is off only if it's still a valid restaurant phone (decision 11.3).
                'delivery_phone_id' => UpdateOrderingRequest::isDeliveryPhone($phoneId) ? (int) $phoneId : null,
                'dine_in_enabled' => (bool) $input['dineIn']['isEnabled'],
                'greeting' => $input['message']['greeting'],
                'closing' => $input['message']['closing'] ?? '',
                'thank_you' => $input['thankYou'],
            ])->save();

            $sent = collect($input['dineIn']['branches'])->keyBy('branchId');

            foreach (Branch::query()->get() as $branch) {
                $setting = $sent->get($branch->slug);

                // A branch left out is switched off; its table count stays (decision 11.2).
                $branch->update($setting === null
                    ? ['dine_in_enabled' => false]
                    : ['dine_in_enabled' => (bool) $setting['isEnabled'], 'table_count' => (int) $setting['tableCount']]);
            }
        });
    }
}
