<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // $employeeId = \App\Models\Employees::first()?->id ?? \App\Models\Employees::factory()->create()->id;

        \App\Models\User::factory()->create([
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@example.com',
            'employees_id' => 2,
            'password' => bcrypt('password'),
        ]);
    }
}
