<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Especialista;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Especialista::firstOrCreate(
            ['email' => 'admin@pandora.com'],
            ['password' => 'password_segura']
        );
    }
}
