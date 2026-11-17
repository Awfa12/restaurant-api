<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\OrderingSetting;
use App\Models\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The ordering settings, two shapes (API-CONTRACT §3, §4 Ordering):
 * - admin(): frontend `OrderingSettings`, every branch (also never set up:
 *   off, 0 tables), a delivery phone gone → `phoneId: ""`;
 * - forPublic(): frontend `OrderingOptions` without `restaurantName` /
 *   `timeZone` (decision 11.1): only what's on and can reach a WhatsApp
 *   number, the numbers worked out here (the public site info has no staff
 *   phones). Four queries.
 *
 * @property array{ordering: OrderingSetting, branches: Collection<int, Branch>, public: bool} $resource
 */
class OrderingResource extends JsonResource
{
    /**
     * The settings as the dashboard edits them.
     */
    public static function admin(): self
    {
        return new self([
            'ordering' => OrderingSetting::current(),
            'branches' => Branch::query()->orderBy('position')->orderBy('id')->get(),
            'public' => false,
        ]);
    }

    /**
     * What the checkout needs.
     */
    public static function forPublic(): self
    {
        return new self([
            'ordering' => OrderingSetting::current()->loadMissing('deliveryPhone'),
            'branches' => Branch::query()
                ->where('dine_in_enabled', true)
                ->where('table_count', '>', 0)
                ->with(['phones' => fn ($query) => $query->where('has_whatsapp', true)])
                ->orderBy('position')
                ->orderBy('id')
                ->get(),
            'public' => true,
        ]);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['ordering' => $ordering, 'branches' => $branches] = $this->resource;
        $message = ['greeting' => $ordering->greeting, 'closing' => $ordering->closing];

        if ($this->resource['public']) {
            return [
                'delivery' => $this->publicDelivery($ordering),
                'dineInBranches' => $ordering->dine_in_enabled ? $this->publicBranches($branches) : [],
                'message' => $message,
                'thankYou' => $ordering->thank_you,
            ];
        }

        return [
            'delivery' => [
                'isEnabled' => $ordering->delivery_enabled,
                'fee' => $ordering->delivery_fee,
                'phoneId' => $ordering->delivery_phone_id === null ? '' : (string) $ordering->delivery_phone_id,
            ],
            'dineIn' => [
                'isEnabled' => $ordering->dine_in_enabled,
                'branches' => $branches->map(fn (Branch $branch) => [
                    'branchId' => $branch->slug,
                    'isEnabled' => $branch->dine_in_enabled,
                    'tableCount' => $branch->table_count,
                ])->values()->all(),
            ],
            'message' => $message,
            'thankYou' => $ordering->thank_you,
        ];
    }

    /**
     * Delivery while on and its phone has WhatsApp, else null (orders couldn't be sent).
     *
     * @return array{fee: int, whatsApp: string}|null
     */
    protected function publicDelivery(OrderingSetting $ordering): ?array
    {
        $phone = $ordering->deliveryPhone;

        return $ordering->delivery_enabled && $phone instanceof Phone && $phone->has_whatsapp
            ? ['fee' => $ordering->delivery_fee, 'whatsApp' => $phone->number]
            : null;
    }

    /**
     * Branches taking table orders, each with its first WhatsApp staff phone
     * (in list order); a branch without one is left out.
     *
     * @param  Collection<int, Branch>  $branches
     * @return array<int, array{id: string, name: string, tableCount: int, whatsApp: string}>
     */
    protected function publicBranches(Collection $branches): array
    {
        return $branches
            ->filter(fn (Branch $branch) => $branch->phones->isNotEmpty())
            ->map(fn (Branch $branch) => [
                'id' => $branch->slug,
                'name' => $branch->name,
                'tableCount' => $branch->table_count,
                'whatsApp' => $branch->phones->first()->number,
            ])
            ->values()
            ->all();
    }
}
