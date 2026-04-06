<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('DEPT-##')),
            'name' => $this->faker->unique()->company(),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
