<?php

namespace Database\Factories;

use App\Models\OrderingSetting;
use App\Models\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderingSetting>
 */
class OrderingSettingFactory extends Factory
{
    /**
     * Define the model's default state: delivery and tables off.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_enabled' => false,
            'delivery_fee' => 0,
            'delivery_phone_id' => null,
            'dine_in_enabled' => false,
            'greeting' => 'مرحبا {restaurant}، بدي اطلب:',
            'closing' => 'شكراً إلكن',
            'thank_you' => 'شكراً {name}!',
        ];
    }

    /**
     * Delivery on, sent to the given restaurant phone.
     */
    public function withDelivery(?Phone $phone = null, int $fee = 150): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_enabled' => true,
            'delivery_fee' => $fee,
            'delivery_phone_id' => $phone ?? Phone::factory(),
        ]);
    }
}
