<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentLog>
 */
class DocumentLogFactory extends Factory
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
            'user_id' => User::factory(),
            'action' => $this->faker->randomElement([
                'created', 'updated', 'routed', 'received', 'rejected', 
                'approved', 'qr_generated', 'qr_scanned', 'shared_access',
                'cloud_share_generated', 'cloud_accessed', 'bulk_uploaded'
            ]),
            'description' => $this->faker->sentence(),
            'metadata' => $this->faker->optional()->randomElements([
                'ip_address' => $this->faker->ipv4(),
                'user_agent' => $this->faker->userAgent(),
                'remarks' => $this->faker->sentence()
            ]),
        ];
    }
}