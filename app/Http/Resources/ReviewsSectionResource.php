<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `ReviewsSettings` (src/types/dashboard.ts): the section's own
 * settings, from the array ReviewsSection stores.
 *
 * @property array{isEnabled: bool, eyebrow: string, title: string, defaultQuestion: string} $resource
 */
class ReviewsSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'isEnabled' => $this->resource['isEnabled'],
            'eyebrow' => $this->resource['eyebrow'],
            'title' => $this->resource['title'],
            'defaultQuestion' => $this->resource['defaultQuestion'],
        ];
    }
}
