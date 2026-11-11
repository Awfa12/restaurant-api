<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductSize;
use App\Support\PublicImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `Product` (src/types/menu.ts). The slug is the id; empty optional
 * fields are left out. Sizes are rebuilt as the one group the dashboard uses
 * (decision 1.4). Needs `category` and `sizes` loaded.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'categoryId' => $this->category->slug,
            'name' => $this->name,
            'description' => $this->description,
            'ingredients' => $this->ingredients,
            'price' => $this->price,
            'image' => $this->when($this->image_path !== null, fn () => PublicImage::url($this->image_path)),
            'imageStyle' => $this->when($this->image_style !== null, fn () => $this->image_style->value),
            'isBestSeller' => $this->is_best_seller,
            'bestSellerOrder' => $this->when($this->is_best_seller && $this->best_seller_order !== null, $this->best_seller_order),
            'isAvailable' => $this->is_available,
            'optionGroups' => $this->when($this->sizes->isNotEmpty(), fn () => [[
                'id' => 'size',
                'name' => $this->sizes_label ?? 'الحجم',
                'required' => true,
                'maxSelect' => 1,
                'options' => $this->sizes->map(fn (ProductSize $size) => array_filter([
                    'id' => (string) $size->id,
                    'name' => $size->name,
                    'shortName' => $size->short_name,
                    'priceDelta' => $size->price_delta,
                ], fn ($value) => $value !== null))->values()->all(),
            ]]),
        ];
    }
}
