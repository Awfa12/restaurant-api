<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\BestSellers;
use App\Actions\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PatchProductRequest;
use App\Http\Requests\Api\V1\Admin\SaveProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard products (API-CONTRACT §4 Products; frontend repository
 * createProduct / saveProduct / updateProduct / deleteProduct). Full saves
 * are PUT, quick changes PATCH (decision 4.2).
 */
class ProductController extends Controller
{
    public function __construct(
        protected SaveProduct $saveProduct,
        protected BestSellers $bestSellers,
    ) {}

    /**
     * GET /admin/products → every product (unavailable ones included), in creation order.
     */
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(Product::with(['category', 'sizes'])->orderBy('id')->get());
    }

    /**
     * POST /admin/products → 201 with the new product (a best seller goes last).
     */
    public function store(SaveProductRequest $request): JsonResponse
    {
        $product = $this->saveProduct->create($request->validated());

        return (new ProductResource($product))->response()->setStatusCode(201);
    }

    /**
     * PUT /admin/products/{id}: the whole product, sizes synced by id.
     */
    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->saveProduct->update($product, $request->validated()));
    }

    /**
     * PATCH /admin/products/{id}: price, availability or best seller from the list.
     */
    public function patch(PatchProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($product, $validated) {
            $wasBestSeller = $product->is_best_seller;

            $product->fill(collect($validated)->only(['price', 'isAvailable', 'isBestSeller'])
                ->mapWithKeys(fn ($value, $key) => [match ($key) {
                    'price' => 'price',
                    'isAvailable' => 'is_available',
                    'isBestSeller' => 'is_best_seller',
                } => $value])
                ->all());
            $product->save();

            $place = isset($validated['bestSellerOrder']) ? (float) $validated['bestSellerOrder'] : null;

            if ($product->is_best_seller !== $wasBestSeller || $place !== null) {
                $this->bestSellers->place($product, $wasBestSeller, $place);
            }
        });

        return new ProductResource($product->refresh()->load(['category', 'sizes']));
    }

    /**
     * DELETE /admin/products/{id} → 204. Its sizes go with it, banner buttons
     * pointing at it lose their target, the best sellers are renumbered.
     */
    public function destroy(Product $product): Response
    {
        DB::transaction(function () use ($product) {
            $product->delete();
            $this->bestSellers->renumber();
        });

        return response()->noContent();
    }
}
