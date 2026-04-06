<?php

namespace Database\Factories;

use App\Models\DocumentType;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_number' => $this->faker->unique()->regexify('[A-Z]{2,3}-\d{4}-\d{4}'),
            'file_name' => $this->faker->words(3, true) . '.pdf',
            'description' => $this->faker->paragraph(),
            'tags' => $this->faker->optional()->randomElements(['finance','bac','urgent','procurement','supplies']),
            'sender_id' => User::factory(),
            'type' => $this->faker->randomElement(['PR', 'PO', 'DV', 'bid', 'award', 'contract', 'other']),
            'security_level' => $this->faker->randomElement(['public','internal','confidential','secret']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'urgent']),
            'status' => 'draft',
            'qr_code_path' => $this->faker->optional()->filePath(),
            'file_path' => $this->faker->optional()->filePath(),
            'department_id' => Department::factory(),
            'document_type_id' => DocumentType::factory(),
            'current_department_id' => Department::factory(),
            'created_by' => User::factory(),
            'barcode' => $this->faker->optional(0.7)->numerify('############'),
            'title' => $this->faker->sentence(4),
            'submission_date' => $this->faker->optional()->date(),
            'deadline' => $this->faker->optional()->dateTimeBetween('now', '+1 year'),
            'metadata' => $this->faker->optional()->randomElements(['key1' => 'value1', 'key2' => 'value2']),
        ];
    }
}