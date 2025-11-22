<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;
use App\Models\Persona;
use App\Models\Rol;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UsuarioAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Buscar o crear persona para el admin
        $personaAdmin = Persona::updateOrCreate(
            ['ci' => '0000000'],
            [
                'nombre' => 'Administrador',
                'apellido' => 'Sistema',
                'celular' => '70000000',
                'sexo' => 'M',
                'fecha_nacimiento' => '1990-01-01',
                'direccion' => 'Sistema',
                'usuario_id' => null // Se actualizará después
            ]
        );

        // Buscar rol ADMIN
        $rolAdmin = Rol::where('nombre_rol', 'ADMIN')->where('activo', true)->first();

        if (!$rolAdmin) {
            $this->command->error('El rol ADMIN no existe. Ejecuta primero RolSeeder.');
            return;
        }

        // Crear usuario admin
        $usuarioAdmin = Usuario::updateOrCreate(
            ['email' => 'admin@sistema.edu'],
            [
                'password' => Hash::make('admin123'), // Cambiar en producción
                'persona_id' => $personaAdmin->id,
                'rol_id' => $rolAdmin->rol_id
            ]
        );

        // Actualizar persona con usuario_id
        $personaAdmin->usuario_id = $usuarioAdmin->usuario_id;
        $personaAdmin->save();

        $this->command->info('Usuario ADMIN creado exitosamente:');
        $this->command->info('Email: admin@sistema.edu');
        $this->command->info('Password: admin123');
        $this->command->warn('IMPORTANTE: Cambia la contraseña en producción!');
    }
}

