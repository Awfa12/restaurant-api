<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `Offer` (src/types/content.ts). The slug is the id; empty optional
 * fields are left out.
 *
 * @mixin Offer
 */
class OfferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'title' => $this->title,
            'items' => $this->items,
            'freeItem' => $this->free_item,
            'price' => $this->price,
            'image' => PublicImage::url($this->image_path),
            'imageStyle' => $this->when($this->image_style !== null, fn () => $this->image_style->value),
            'badge' => $this->when(filled($this->badge), $this->badge),
            'isActive' => $this->is_active,
        ];
    }
}
