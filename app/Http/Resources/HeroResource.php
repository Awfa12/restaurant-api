<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `HeroContent` (src/types/content.ts), from the array HeroContent
 * stores. An empty badge is left out.
 *
 * @property array{badge?: string, headline: string, primaryActionLabel: string, secondaryActionLabel: string, features: array<int, array{label: string, icon: string}>} $resource
 */
class HeroResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $badge = $this->resource['badge'] ?? null;

        return [
            'badge' => $this->when(filled($badge), $badge),
            'headline' => $this->resource['headline'],
            'primaryActionLabel' => $this->resource['primaryActionLabel'],
            'secondaryActionLabel' => $this->resource['secondaryActionLabel'],
            'features' => array_map(
                fn (array $feature) => ['label' => $feature['label'], 'icon' => $feature['icon']],
                $this->resource['features'],
            ),
        ];
    }
}
