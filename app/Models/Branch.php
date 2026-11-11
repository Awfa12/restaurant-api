<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A restaurant branch. `slug` is the public id and is printed in the table QR
 * codes, so it never changes by itself. The dine-in settings live here.
 */
#[Fillable(['slug', 'name', 'address', 'map_url', 'dine_in_enabled', 'table_count', 'position'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * The branch's floor staff phones, in list order (the first with WhatsApp
     * gets the table orders).
     *
     * @return HasMany<Phone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(Phone::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dine_in_enabled' => 'boolean',
            'table_count' => 'integer',
            'position' => 'integer',
        ];
    }
}
