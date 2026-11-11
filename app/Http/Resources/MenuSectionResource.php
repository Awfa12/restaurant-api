<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Frontend `MenuSection` (src/types/menu.ts): a category with its products,
 * as the menu page renders it. Needs `products.sizes` loaded.
 *
 * @mixin Category
 */
class MenuSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{category: CategoryResource, products: mixed}
     */
    public function toArray(Request $request): array
    {
        return [
            'category' => new CategoryResource($this->resource),
            'products' => ProductResource::collection($this->products),
        ];
    }
}
