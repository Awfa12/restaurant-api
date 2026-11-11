<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\OrderedList;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\MoveRequest;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\PublicImage;
use App\Support\Slugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Dashboard categories (API-CONTRACT §4 Categories; frontend repository
 * createCategory / renameCategory / setCategoryImage / moveCategory / deleteCategory).
 */
class CategoryController extends Controller
{
    public function __construct(protected OrderedList $orderedList) {}

    /**
     * GET /admin/categories → every category, in menu order.
     */
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->orderedList->ordered(Category::query())->get());
    }

    /**
     * POST /admin/categories { name, slug? } → 201, last in the order.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(fn () => Category::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Slugs::generate('cat', ['categories']),
            'position' => $this->orderedList->nextPosition(Category::query()),
        ]));

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * PATCH /admin/categories/{id} { name?, slug?, image? }: only what is sent
     * changes; an empty slug keeps the current one; `image: null` removes the photo.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $category->name = $validated['name'];
        }

        if (! empty($validated['slug'])) {
            $category->slug = $validated['slug'];
        }

        if (array_key_exists('image', $validated)) {
            $category->image_path = $validated['image'] === null ? null : PublicImage::pathFromUrl($validated['image']);
        }

        $category->save();

        return new CategoryResource($category);
    }

    /**
     * POST /admin/categories/{id}/move { direction } → every category, in the new order.
     */
    public function move(MoveRequest $request, Category $category): AnonymousResourceCollection
    {
        $this->orderedList->move($category, $request->direction(), Category::query());

        return $this->index();
    }

    /**
     * DELETE /admin/categories/{id} → 204; 409 while it still has products.
     */
    public function destroy(Category $category): Response
    {
        $productCount = $category->products()->count();

        if ($productCount > 0) {
            throw new ConflictHttpException("ما فيك تحذف قسم فيه أصناف ({$productCount}). انقلها لقسم تاني أو احذفها أول.");
        }

        DB::transaction(function () use ($category) {
            $category->delete();
            $this->orderedList->renumber(Category::query());
        });

        return response()->noContent();
    }
}
