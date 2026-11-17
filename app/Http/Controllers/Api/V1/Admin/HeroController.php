<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateHeroRequest;
use App\Http\Resources\HeroResource;
use App\Support\HeroContent;

/**
 * Dashboard hero texts (API-CONTRACT §4 Hero; frontend saveHero): one whole
 * record, read and saved together.
 */
class HeroController extends Controller
{
    /**
     * GET /admin/hero → the stored HeroContent.
     */
    public function show(): HeroResource
    {
        return new HeroResource(HeroContent::get());
    }

    /**
     * PUT /admin/hero: the whole hero; a missing badge is cleared.
     */
    public function update(UpdateHeroRequest $request): HeroResource
    {
        $input = $request->validated();

        return new HeroResource(HeroContent::replace(array_filter([
            'badge' => filled($input['badge'] ?? null) ? $input['badge'] : null,
            'headline' => $input['headline'],
            'primaryActionLabel' => $input['primaryActionLabel'],
            'secondaryActionLabel' => $input['secondaryActionLabel'],
            'features' => array_map(
                fn (array $feature) => ['label' => $feature['label'], 'icon' => $feature['icon']],
                array_values($input['features']),
            ),
        ], fn ($value) => $value !== null)));
    }
}
