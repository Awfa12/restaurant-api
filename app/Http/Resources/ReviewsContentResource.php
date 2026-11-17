<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Frontend `ReviewsContent` (src/types/content.ts), flat: the section's
 * settings with its reviews next to them. The admin GET sends every review,
 * the public GET only the visible ones (decision 6.2).
 *
 * @property array{section: array<string, mixed>, reviews: Collection<int, Review>} $resource
 */
class ReviewsContentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...(new ReviewsSectionResource($this->resource['section']))->toArray($request),
            'reviews' => ReviewResource::collection($this->resource['reviews']),
        ];
    }
}
