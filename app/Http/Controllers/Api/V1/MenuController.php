<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuSectionResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * GET /menu (public, API-CONTRACT §3): what the frontend's getMenu() returns.
 * The site builds the menu page, best sellers, product pages and the cart
 * catalogue from it.
 */
class MenuController extends Controller
{
    /**
     * Categories in menu order, each with its products (sizes included).
     * Empty categories are left out; unavailable products are included (the
     * site shows them dimmed). Three queries, whatever the menu's size.
     */
    public function __invoke(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->whereHas('products')
            ->with('products.sizes')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return MenuSectionResource::collection($categories);
    }
}
