<?php

namespace Database\Seeders;

use App\Models\Lote;
use Illuminate\Database\Seeder;

class LoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Lote::create([
            'nombre' => 'Lote Premium: Juguetes con Botellas Recicladas',
            'descripcion' => 'Manuales para elaborar juguetes interactivos para perros y gatos usando botellas plásticas y cartón.',
            'precio' => 19.90,
            'estado' => 'activo',
        ]);

        Lote::create([
            'nombre' => 'Lote Premium: Accesorios con Telas Recicladas',
            'descripcion' => 'Manuales para elaborar collares, correas y camas usando retazos de tela y ropa en desuso.',
            'precio' => 24.90,
            'estado' => 'activo',
        ]);
    }
}
