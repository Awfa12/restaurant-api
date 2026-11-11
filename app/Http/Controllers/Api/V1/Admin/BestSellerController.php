<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\BestSellers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MoveRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The home "الأكثر مبيعاً" slider order (API-CONTRACT §4 Best sellers;
 * frontend repository moveBestSeller).
 */
class BestSellerController extends Controller
{
    public function __construct(protected BestSellers $bestSellers) {}

    /**
     * POST /admin/best-sellers/{id}/move { direction } → the best sellers in the new order.
     */
    public function move(MoveRequest $request, Product $product): AnonymousResourceCollection
    {
        if (! $product->is_best_seller) {
            // The dashboard's text (local-repository moveBestSeller).
            throw new ConflictHttpException('هالصنف مش بالأكثر مبيعاً. حدّث الصفحة.');
        }

        $this->bestSellers->move($product, $request->direction());

        return ProductResource::collection($this->bestSellers->ordered()->with(['category', 'sizes'])->get());
    }
}
