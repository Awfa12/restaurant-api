<?php

namespace App\Models;

use Database\Factories\PhoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A restaurant phone, or (with a branch) a branch's floor staff phone, which
 * is never public.
 */
#[Fillable(['number', 'has_whatsapp', 'branch_id', 'position'])]
class Phone extends Model
{
    /** @use HasFactory<PhoneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_whatsapp' => 'boolean',
            'position' => 'integer',
        ];
    }
}
