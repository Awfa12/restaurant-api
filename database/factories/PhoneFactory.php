<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Phone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phone>
 */
class PhoneFactory extends Factory
{
    /**
     * Define the model's default state: a public restaurant number with WhatsApp.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => '+9639'.fake()->numerify('########'),
            'has_whatsapp' => true,
            'branch_id' => null,
            'position' => fn () => (int) Phone::max('position') + 1,
        ];
    }

    /**
     * A branch's floor staff number (gets its table orders, never public).
     */
    public function forBranch(?Branch $branch = null): static
    {
        return $this->state(fn (array $attributes) => [
            'branch_id' => $branch ?? Branch::factory(),
        ]);
    }

    /**
     * A number without WhatsApp (calls only).
     */
    public function withoutWhatsApp(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_whatsapp' => false,
        ]);
    }
}
