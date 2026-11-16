<?php

namespace App\Http\Resources;

use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Frontend `OffersContent` (src/types/content.ts), flat: the section's
 * settings with its offers next to them. The admin GET sends every offer, the
 * public GET only the active ones (API-CONTRACT §3, §4).
 *
 * @property array{section: array<string, mixed>, offers: Collection<int, Offer>} $resource
 */
class OffersContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new OffersSectionResource($this->resource['section']))->toArray($request),
            'offers' => OfferResource::collection($this->resource['offers']),
        ];
    }
}
