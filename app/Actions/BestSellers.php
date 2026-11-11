<?php

namespace App\Actions;

use App\Enums\MoveDirection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The home "الأكثر مبيعاً" slider: best sellers numbered 1…n in
 * `best_seller_order`, no gaps (BACKEND_REQUIREMENTS §4.2). Same behaviour as
 * the dashboard's draft (local-repository placeNewBestSeller / numberBestSellers).
 */
class BestSellers
{
    public const COLUMN = 'best_seller_order';

    public function __construct(protected OrderedList $orderedList) {}

    /**
     * Every best seller, as a query.
     *
     * @return Builder<Product>
     */
    public function query(): Builder
    {
        return Product::query()->where('is_best_seller', true);
    }

    /**
     * Places the product after a save and renumbers every best seller 1…n.
     * A product that just became one goes last, or at `$place` when the
     * dashboard's undo sends its old place (a fraction like 2.5 lands
     * between 2 and 3). A product that stopped being one loses its number.
     */
    public function place(Product $product, bool $wasBestSeller, ?float $place = null): void
    {
        DB::transaction(function () use ($product, $wasBestSeller, $place) {
            if (! $product->is_best_seller) {
                $product->forceFill([self::COLUMN => null])->save();
                $this->renumber();

                return;
            }

            $placeOfProduct = match (true) {
                $place !== null => $place,
                ! $wasBestSeller => INF,
                default => (float) ($product->getOriginal(self::COLUMN) ?? INF),
            };

            $sellers = $this->query()->lockForUpdate()->get()
                ->sortBy([
                    fn (Product $a, Product $b) => $this->placeOf($a, $product, $placeOfProduct) <=> $this->placeOf($b, $product, $placeOfProduct),
                    fn (Product $a, Product $b) => $a->id <=> $b->id,
                ])
                ->values();

            foreach ($sellers as $index => $seller) {
                if ($seller->getAttribute(self::COLUMN) !== $index + 1) {
                    $seller->forceFill([self::COLUMN => $index + 1])->save();
                }
            }

            $product->refresh();
        });
    }

    /**
     * Numbers the best sellers 1…n in their current order (after a delete).
     */
    public function renumber(): void
    {
        $this->orderedList->renumber($this->query(), self::COLUMN);
    }

    /**
     * One step up or down in the slider.
     */
    public function move(Product $product, MoveDirection $direction): void
    {
        $this->orderedList->move($product, $direction, $this->query(), self::COLUMN);
    }

    /**
     * Every best seller in slider order.
     *
     * @return Builder<Product>
     */
    public function ordered(): Builder
    {
        return $this->orderedList->ordered($this->query(), self::COLUMN);
    }

    /**
     * The sort key: the product being placed uses its new place.
     */
    protected function placeOf(Product $candidate, Product $placed, float $placeOfPlaced): float
    {
        return $candidate->is($placed) ? $placeOfPlaced : (float) ($candidate->getAttribute(self::COLUMN) ?? INF);
    }
}
