<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Persona;
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear persona administrativa
        $persona = Persona::updateOrCreate(
            ['ci' => '12345678'],
            [
                'ci' => '12345678',
                'nombre' => 'Administrador',
                'apellido' => 'Sistema',
                'celular' => '0987654321',
                'fecha_nacimiento' => '1990-01-01',
                'direccion' => 'Dirección administrativa',
                'fotografia' => null
            ]
        );

        // Crear usuario administrativo
        Usuario::updateOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin123'),
                'persona_id' => $persona->persona_id
            ]
        );

        $this->command->info('✅ Usuario administrativo creado exitosamente');
        $this->command->info('📧 Email: admin@sistema.com');
        $this->command->info('🔑 Password: admin123');
    }
}
