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
        // Buscar el rol ADMIN
        $rolAdmin = \App\Models\Rol::where('nombre_rol', 'ADMIN')->first();

        if (!$rolAdmin) {
            $this->command->error('❌ No se encontró el rol ADMIN. Ejecuta primero: php artisan db:seed --class=RolesSeeder');
            return;
        }

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

        // Crear usuario administrativo con rol_id
        $usuario = Usuario::updateOrCreate(
            ['email' => 'admin@sistema.com'],
            [
                'email' => 'admin@sistema.com',
                'password' => Hash::make('admin123'),
                'persona_id' => $persona->id,
                'rol_id' => $rolAdmin->rol_id  // IMPORTANTE: Asignar rol
            ]
        );

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('   ✅ USUARIO ADMINISTRADOR CREADO');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('📧 Email: admin@sistema.com');
        $this->command->info('🔑 Password: admin123');
        $this->command->info('👤 CI: 12345678');
        $this->command->info('🛡️  Rol: ADMIN (ID: ' . $rolAdmin->rol_id . ')');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('');
    }
}
