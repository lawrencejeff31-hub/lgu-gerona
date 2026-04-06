<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->words(2, true) . ' ' . $this->faker->randomElement(['Request', 'Document', 'Form', 'Report']);
        return [
            'name' => $name,
            'code' => strtoupper($this->faker->lexify('???')) . $this->faker->randomNumber(2),
            'description' => $this->faker->optional()->sentence(),
            'prefix' => strtoupper($this->faker->lexify('DOC-??')),
            'requires_approval' => $this->faker->boolean(30),
            'is_active' => true,
        ];
    }
}
