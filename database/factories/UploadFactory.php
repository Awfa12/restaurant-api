<?php

namespace Database\Factories;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Upload>
 */
class UploadFactory extends Factory
{
    /**
     * Define the model's default state: an 800 px WebP, as the dashboard prepares it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'path' => 'uploads/'.fake()->unique()->uuid().'.webp',
            'width' => 800,
            'height' => 800,
            'size_bytes' => fake()->numberBetween(20_000, 400_000),
        ];
    }
}
