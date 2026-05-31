<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        Area::firstOrCreate(['nombre' => 'Psicología'], ['requiere_aprobacion_estricta' => false]);
        Area::firstOrCreate(['nombre' => 'Medicina General'], ['requiere_aprobacion_estricta' => false]);
        Area::firstOrCreate(['nombre' => 'Fisioterapia'], ['requiere_aprobacion_estricta' => false]);
        Area::firstOrCreate(['nombre' => 'Nutrición'], ['requiere_aprobacion_estricta' => false]);
    }
}
