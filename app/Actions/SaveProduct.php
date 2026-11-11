<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSize;
use App\Support\PublicImage;
use App\Support\Slugs;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product or saves it whole (POST, PUT: decision 4.2), sizes
 * included. Sizes are saved by id (API-CONTRACT §4.1): sent with an id →
 * updated, `id: ""` → created, missing → deleted. Cart lines keep the size id.
 * All in one transaction.
 */
class SaveProduct
{
    public function __construct(protected BestSellers $bestSellers) {}

    /**
     * POST /admin/products.
     *
     * @param  array<string, mixed>  $input  SaveProductRequest::validated()
     */
    public function create(array $input): Product
    {
        return DB::transaction(function () use ($input) {
            $product = new Product([
                'slug' => ($input['slug'] ?? null) ?: Slugs::generate('item', ['products', 'offers']),
            ]);
            $this->fill($product, $input);
            $product->save();

            $this->syncSizes($product, $input);
            $this->bestSellers->place($product, wasBestSeller: false, place: $this->place($input));

            return $this->fresh($product);
        });
    }

    /**
     * PUT /admin/products/{id}: the whole product. A missing optional field
     * (image, imageStyle, optionGroups) is cleared; the slider place is kept
     * (the form doesn't hold it).
     *
     * @param  array<string, mixed>  $input  SaveProductRequest::validated()
     */
    public function update(Product $product, array $input): Product
    {
        return DB::transaction(function () use ($product, $input) {
            $wasBestSeller = $product->is_best_seller;

            if (! empty($input['slug'])) {
                $product->slug = $input['slug'];
            }

            $this->fill($product, $input);
            $product->save();

            $this->syncSizes($product, $input);
            $this->bestSellers->place($product, $wasBestSeller);

            return $this->fresh($product);
        });
    }

    /**
     * The product's own columns from the request.
     *
     * @param  array<string, mixed>  $input
     */
    protected function fill(Product $product, array $input): void
    {
        $image = $input['image'] ?? null;
        $sizes = $input['optionGroups'][0]['options'] ?? [];

        $product->fill([
            'category_id' => Category::where('slug', $input['categoryId'])->value('id'),
            'name' => $input['name'],
            'description' => $input['description'] ?? '',
            'ingredients' => $input['ingredients'],
            'price' => (int) $input['price'],
            'image_path' => $image === null ? null : PublicImage::pathFromUrl($image),
            'image_style' => $input['imageStyle'] ?? null,
            'is_available' => $input['isAvailable'],
            'is_best_seller' => $input['isBestSeller'],
            'sizes_label' => $sizes === [] ? null : ($input['optionGroups'][0]['name'] ?? null),
        ]);
    }

    /**
     * Sizes by id, in the order sent. The request already refused ids that
     * aren't this product's.
     *
     * @param  array<string, mixed>  $input
     */
    protected function syncSizes(Product $product, array $input): void
    {
        $sent = array_values($input['optionGroups'][0]['options'] ?? []);
        $keptIds = collect($sent)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        $product->sizes()->whereNotIn('id', $keptIds)->delete();

        foreach ($sent as $index => $option) {
            $attributes = [
                'name' => trim($option['name']),
                'short_name' => filled($option['shortName'] ?? null) ? trim($option['shortName']) : null,
                'price_delta' => (int) $option['priceDelta'],
                'position' => $index + 1,
            ];

            if (filled($option['id'] ?? null)) {
                ProductSize::whereKey((int) $option['id'])->whereBelongsTo($product)->update($attributes);
            } else {
                $product->sizes()->create($attributes);
            }
        }
    }

    /**
     * The slider place sent on create (the dashboard's undo of a delete), if any.
     *
     * @param  array<string, mixed>  $input
     */
    protected function place(array $input): ?float
    {
        return isset($input['bestSellerOrder']) ? (float) $input['bestSellerOrder'] : null;
    }

    /**
     * The saved product with what its JSON needs.
     */
    protected function fresh(Product $product): Product
    {
        return $product->refresh()->load(['category', 'sizes']);
    }
}
