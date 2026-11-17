<?php

namespace App\Http\Resources;

use App\Models\Branch;
use App\Models\Phone;
use App\Support\SiteInfoSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Frontend `SiteInfo` (src/types/content.ts): the texts, phones and branches.
 * Branch ids are their slugs (printed in the QR codes), phone ids the
 * database ids. Two views:
 * - admin (admin()): every phone, a staff phone with its `branchId`;
 * - public (forPublic()): the branch staff phones are left out (never public).
 * Three queries either way.
 *
 * @property array{settings: array<string, mixed>, branches: Collection<int, Branch>, phones: Collection<int, Phone>} $resource
 */
class SiteInfoResource extends JsonResource
{
    /**
     * The restaurant info as the dashboard edits it.
     */
    public static function admin(): self
    {
        return new self(self::load(Phone::query()));
    }

    /**
     * The restaurant info as customers see it (footer, JSON-LD, mobile menu).
     */
    public static function forPublic(): self
    {
        return new self(self::load(Phone::query()->whereNull('branch_id')));
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['settings' => $settings, 'branches' => $branches, 'phones' => $phones] = $this->resource;
        $slugs = $branches->pluck('slug', 'id');

        return [
            'tagline' => $settings['tagline'],
            'phones' => $phones->map(fn (Phone $phone) => array_filter([
                'id' => (string) $phone->id,
                'number' => $phone->number,
                'hasWhatsApp' => $phone->has_whatsapp,
                'branchId' => $phone->branch_id === null ? null : $slugs[$phone->branch_id],
            ], fn ($value) => $value !== null))->values()->all(),
            'branches' => $branches->map(fn (Branch $branch) => array_filter([
                'id' => $branch->slug,
                'name' => $branch->name,
                'address' => $branch->address,
                'mapUrl' => $branch->map_url,
            ], fn ($value) => $value !== null))->values()->all(),
            'openingHours' => $this->when(filled($settings['openingHours'] ?? null), fn () => $settings['openingHours']),
            'openingHoursSchema' => $this->when(! empty($settings['openingHoursSchema']), fn () => $settings['openingHoursSchema']),
            'socialLinks' => $this->when(! empty($settings['socialLinks']), fn () => array_map(
                fn (array $link) => ['platform' => $link['platform'], 'url' => $link['url']],
                $settings['socialLinks'],
            )),
        ];
    }

    /**
     * The settings row, the branches and the given phones, each in list order.
     *
     * @param  Builder<Phone>  $phones
     * @return array{settings: array<string, mixed>, branches: Collection<int, Branch>, phones: Collection<int, Phone>}
     */
    protected static function load(Builder $phones): array
    {
        return [
            'settings' => SiteInfoSettings::get(),
            'branches' => Branch::query()->orderBy('position')->orderBy('id')->get(),
            'phones' => $phones->orderBy('position')->orderBy('id')->get(),
        ];
    }
}
