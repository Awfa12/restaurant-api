<?php

namespace App\Models;

use App\Enums\ImageStyle;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A menu product. `slug` is the public id (/menu/<slug>), shared with offers.
 * Its sizes are one group named `sizes_label` (decision 1.4).
 */
#[Fillable([
    'slug',
    'category_id',
    'name',
    'description',
    'ingredients',
    'price',
    'image_path',
    'image_style',
    'is_available',
    'is_best_seller',
    'best_seller_order',
    'sizes_label',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductSize, $this>
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'price' => 'integer',
            'image_style' => ImageStyle::class,
            'is_available' => 'boolean',
            'is_best_seller' => 'boolean',
            'best_seller_order' => 'integer',
        ];
    }
}
