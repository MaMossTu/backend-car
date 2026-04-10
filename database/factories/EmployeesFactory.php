<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees>
 */
class EmployeesFactory extends Factory
{
    protected $model = \App\Models\Employees::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'branch_id' => 1, // You may want to adjust this if you have branches
        ];
    }
}
