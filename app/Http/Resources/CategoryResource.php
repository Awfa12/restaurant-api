<?php

namespace App\Http\Resources;

use App\Models\Category;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `Category` (src/types/menu.ts). The slug is the id; no photo →
 * no `image` key (API-CONTRACT §2: empty optional fields left out).
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'image' => $this->when($this->image_path !== null, fn () => PublicImage::url($this->image_path)),
            'order' => $this->position,
        ];
    }
}
