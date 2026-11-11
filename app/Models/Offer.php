<?php

namespace App\Models;

use App\Enums\ImageStyle;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A "buy X, get Y free" offer. `slug` is the public id, shared with products.
 */
#[Fillable([
    'slug',
    'title',
    'items',
    'free_item',
    'price',
    'image_path',
    'image_style',
    'badge',
    'is_active',
    'position',
])]
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items' => 'array',
            'price' => 'integer',
            'image_style' => ImageStyle::class,
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
