<?php

namespace App\Models;

use App\Enums\BannerTargetKind;
use Database\Factories\BannerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A menu banner slide. The button (if any) points at a target kind and, for
 * a category or product, its key; a deleted target leaves the key null.
 */
#[Fillable([
    'eyebrow',
    'title',
    'image_path',
    'image_alt',
    'action_label',
    'target_kind',
    'target_category_id',
    'target_product_id',
    'is_active',
    'position',
])]
class Banner extends Model
{
    /** @use HasFactory<BannerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Category, $this>
     */
    public function targetCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'target_category_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_kind' => BannerTargetKind::class,
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
