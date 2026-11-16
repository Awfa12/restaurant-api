<?php

namespace App\Http\Resources;

use App\Support\OffersSection;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `OffersSettings` (src/types/dashboard.ts): the section's own
 * settings, from the array OffersSection stores. The photo is sent as a URL
 * and left out when there is none.
 *
 * @property array{isEnabled: bool, eyebrow: string, title: string, menuCardImagePath?: string} $resource
 */
class OffersSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $imagePath = $this->resource['menuCardImagePath'] ?? null;

        return [
            'isEnabled' => $this->resource['isEnabled'],
            'eyebrow' => $this->resource['eyebrow'],
            'title' => $this->resource['title'],
            'menuCardImage' => $this->when(filled($imagePath), fn () => PublicImage::url($imagePath)),
        ];
    }

    /**
     * The stored section.
     */
    public static function current(): self
    {
        return new self(OffersSection::get());
    }
}
