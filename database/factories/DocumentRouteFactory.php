<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentRoute>
 */
class DocumentRouteFactory extends Factory
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
            'from_office_id' => Department::factory(),
            'to_office_id' => Department::factory(),
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(['sent', 'received', 'rejected', 'approved']),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}