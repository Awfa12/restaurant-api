<?php

namespace App\Models;

use Database\Factories\OrderingSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The ordering settings (exactly one row). The per-branch dine-in settings
 * are columns on branches.
 */
#[Fillable([
    'delivery_enabled',
    'delivery_fee',
    'delivery_phone_id',
    'dine_in_enabled',
    'greeting',
    'closing',
    'thank_you',
])]
class OrderingSetting extends Model
{
    /** @use HasFactory<OrderingSettingFactory> */
    use HasFactory;

    /**
     * The restaurant phone that receives delivery orders.
     *
     * @return BelongsTo<Phone, $this>
     */
    public function deliveryPhone(): BelongsTo
    {
        return $this->belongsTo(Phone::class, 'delivery_phone_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_enabled' => 'boolean',
            'delivery_fee' => 'integer',
            'dine_in_enabled' => 'boolean',
        ];
    }
}
