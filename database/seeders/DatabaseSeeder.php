<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@boutique.com',
            'role' => \App\Enums\UserRole::ADMIN,
        ]);

        User::factory()->create([
            'name' => 'Client Test',
            'email' => 'client@boutique.com',
        ]);
    }
}
