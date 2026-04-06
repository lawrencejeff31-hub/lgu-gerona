<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QRCode>
 */
class QRCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'token' => Str::random(32),
            'qr_image_path' => 'qrcodes/' . $this->faker->uuid() . '.png',
            'scan_count' => $this->faker->numberBetween(0, 100),
            'last_scanned_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
        ];
    }
}