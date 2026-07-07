<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'nombres' => 'Administrador',
            'apellidos' => 'Manual-Pets',
            'dni' => '00000001',
            'direccion' => 'Av. Principal 123, Lima',
            'email' => 'admin@manualpets.test',
            'password' => Hash::make('password'),
            'rol' => 'admin',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        User::create([
            'nombres' => 'Usuario',
            'apellidos' => 'De Prueba',
            'dni' => '00000002',
            'direccion' => 'Jr. Los Olivos 456, Lima',
            'email' => 'usuario@manualpets.test',
            'password' => Hash::make('password'),
            'rol' => 'usuario',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $this->call([
            LoteSeeder::class,
            ManualSeeder::class,
        ]);
    }
}
