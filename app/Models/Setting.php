<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Content saved whole as JSON, one row per key (docs/DATABASE.md §3):
 * hero, about, offers_section, reviews_section, site_info.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
