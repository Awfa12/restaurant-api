<?php

namespace App\Actions;

use App\Enums\MoveDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A list kept in order by an integer column, 1…n with no gaps (decision 1.3):
 * categories, offers, banners, reviews (`position`) and best sellers
 * (`best_seller_order`). No unique index on the column, so a move is a plain
 * swap; every change renumbers inside a transaction.
 */
class OrderedList
{
    /**
     * The place for a new item: after the last one.
     *
     * @param  Builder<Model>  $list
     */
    public function nextPosition(Builder $list, string $column = 'position'): int
    {
        return (int) $list->max($column) + 1;
    }

    /**
     * Moves the item one step up or down. At either end nothing changes
     * (API-CONTRACT §4.2: 200 with the list unchanged).
     *
     * @param  Builder<Model>  $list  every item of the list the item is in
     */
    public function move(Model $item, MoveDirection $direction, Builder $list, string $column = 'position'): void
    {
        DB::transaction(function () use ($item, $direction, $list, $column) {
            $items = $this->ordered($list, $column)->lockForUpdate()->get()->values();
            $from = $items->search(fn (Model $candidate) => $candidate->is($item));
            $to = $direction === MoveDirection::Up ? $from - 1 : $from + 1;

            if ($from !== false && $to >= 0 && $to < $items->count()) {
                [$items[$from], $items[$to]] = [$items[$to], $items[$from]];
            }

            $this->saveOrder($items->all(), $column);
        });
    }

    /**
     * Numbers the list 1…n in its current order (after a delete, or to heal gaps).
     *
     * @param  Builder<Model>  $list
     */
    public function renumber(Builder $list, string $column = 'position'): void
    {
        DB::transaction(function () use ($list, $column) {
            $this->saveOrder($this->ordered($list, $column)->lockForUpdate()->get()->all(), $column);
        });
    }

    /**
     * The list's order: by the column, then by id for ties.
     *
     * @param  Builder<Model>  $list
     * @return Builder<Model>
     */
    public function ordered(Builder $list, string $column = 'position'): Builder
    {
        return (clone $list)->reorder()->orderBy($column)->orderBy('id');
    }

    /**
     * Writes 1…n in the given order, only for the rows that change.
     *
     * @param  array<int, Model>  $items
     */
    protected function saveOrder(array $items, string $column): void
    {
        foreach (array_values($items) as $index => $model) {
            if ($model->getAttribute($column) !== $index + 1) {
                $model->forceFill([$column => $index + 1])->save();
            }
        }
    }
}
