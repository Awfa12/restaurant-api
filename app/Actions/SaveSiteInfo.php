<?php

namespace App\Actions;

use App\Models\Branch;
use App\Models\OrderingSetting;
use App\Models\Phone;
use App\Support\SiteInfoSettings;
use App\Support\Slugs;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * PUT /admin/site-info: the whole restaurant info, in one transaction
 * (API-CONTRACT §4 Site info, §4.1). Branches and phones are saved by id:
 * sent with an id → updated, `id: ""` → created, missing → deleted. A deleted
 * branch takes its staff phones and table settings with it (cascade, and the
 * settings are branch columns); a sent phone of a deleted branch is dropped
 * (decision 10.2). Refused (409) when the orders that are on would be left
 * without a WhatsApp number (frontend orderingConflicts).
 */
class SaveSiteInfo
{
    /**
     * @param  array<string, mixed>  $input  UpdateSiteInfoRequest::validated()
     */
    public function save(array $input): void
    {
        $branches = array_values($input['branches']);
        $keptSlugs = collect($branches)->pluck('id')->filter()->values();
        // A staff phone of a branch deleted in this save goes with it.
        $phones = collect($input['phones'])
            ->filter(fn (array $phone) => blank($phone['branchId'] ?? null) || $keptSlugs->contains($phone['branchId']))
            ->values();

        DB::transaction(function () use ($input, $branches, $keptSlugs, $phones) {
            $this->refuseOrderingConflicts($branches, $phones);

            SiteInfoSettings::replace(array_filter([
                'tagline' => $input['tagline'],
                'openingHours' => filled($input['openingHours'] ?? null) ? $input['openingHours'] : null,
                'openingHoursSchema' => filled($input['openingHours'] ?? null) ? array_values($input['openingHoursSchema']) : null,
                'socialLinks' => empty($input['socialLinks']) ? null : array_map(
                    fn (array $link) => ['platform' => $link['platform'], 'url' => $link['url']],
                    array_values($input['socialLinks']),
                ),
            ], fn ($value) => $value !== null));

            // Phones first: a staff phone moved to the restaurant in the same save
            // that deletes its old branch would otherwise go with the cascade.
            // Phones only point at saved branches (a new one is saved first).
            $this->syncPhones($phones, Branch::query()->pluck('id', 'slug')->all());
            $this->syncBranches($branches, $keptSlugs);
        });
    }

    /**
     * Delivery while on needs its phone with WhatsApp; a branch taking table
     * orders needs a WhatsApp phone of its own. One Arabic sentence per problem.
     *
     * @param  array<int, array<string, mixed>>  $branches
     * @param  Collection<int, array<string, mixed>>  $phones
     */
    protected function refuseOrderingConflicts(array $branches, Collection $phones): void
    {
        $ordering = OrderingSetting::query()->lockForUpdate()->first();

        if ($ordering === null) {
            return;
        }

        $conflicts = [];
        $hasWhatsApp = fn (callable $matches) => $phones->contains(fn (array $phone) => $matches($phone) && $phone['hasWhatsApp']);

        if ($ordering->delivery_enabled
            && ! $hasWhatsApp(fn (array $phone) => (string) ($phone['id'] ?? '') === (string) $ordering->delivery_phone_id)) {
            $conflicts[] = 'طلبات التوصيل بدها رقم عليه واتساب: خلّي رقم التوصيل، أو غيّره بدل ما تحذفه.';
        }

        if ($ordering->dine_in_enabled) {
            $withTables = Branch::query()->where('dine_in_enabled', true)->where('table_count', '>', 0)->pluck('slug');

            foreach ($branches as $branch) {
                $slug = $branch['id'] ?? null;

                if (filled($slug) && $withTables->contains($slug)
                    && ! $hasWhatsApp(fn (array $phone) => ($phone['branchId'] ?? null) === $slug)) {
                    $conflicts[] = "طاولات {$branch['name']} بدها رقم واتساب تابع للفرع.";
                }
            }
        }

        if ($conflicts !== []) {
            throw new ConflictHttpException(implode(' ', $conflicts));
        }
    }

    /**
     * Branches in the order sent. A saved branch keeps its slug (printed in
     * the QR codes) and its table settings; a new one takes its «رابط» or a
     * neutral slug.
     *
     * @param  array<int, array<string, mixed>>  $branches
     * @param  Collection<int, string>  $keptSlugs
     */
    protected function syncBranches(array $branches, Collection $keptSlugs): void
    {
        Branch::query()->whereNotIn('slug', $keptSlugs)->delete();

        $saved = Branch::query()->get()->keyBy('slug');

        foreach ($branches as $index => $branch) {
            $attributes = [
                'name' => $branch['name'],
                'address' => $branch['address'],
                'map_url' => filled($branch['mapUrl'] ?? null) ? $branch['mapUrl'] : null,
                'position' => $index + 1,
            ];

            if (filled($branch['id'] ?? null)) {
                $saved[$branch['id']]->update($attributes);
            } else {
                $slug = filled($branch['slug'] ?? null) ? $branch['slug'] : Slugs::generate('branch', ['branches']);
                Branch::create([...$attributes, 'slug' => $slug]);
            }
        }
    }

    /**
     * Phones in the order sent: it decides which branch phone gets the table
     * orders (the first with WhatsApp).
     *
     * @param  Collection<int, array<string, mixed>>  $phones
     * @param  array<string, int>  $branchIds  saved branches, slug => database id
     */
    protected function syncPhones(Collection $phones, array $branchIds): void
    {
        Phone::query()->whereNotIn('id', $phones->pluck('id')->filter()->map(fn ($id) => (int) $id))->delete();

        foreach ($phones as $index => $phone) {
            $attributes = [
                'number' => $phone['number'],
                'has_whatsapp' => (bool) $phone['hasWhatsApp'],
                'branch_id' => filled($phone['branchId'] ?? null) ? $branchIds[$phone['branchId']] : null,
                'position' => $index + 1,
            ];

            if (filled($phone['id'] ?? null)) {
                Phone::query()->whereKey((int) $phone['id'])->update($attributes);
            } else {
                Phone::create($attributes);
            }
        }
    }
}
