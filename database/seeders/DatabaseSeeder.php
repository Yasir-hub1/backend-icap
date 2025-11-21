<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('   🌱 INICIANDO SEEDERS DEL SISTEMA ACADÉMICO');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('');

        // IMPORTANTE: Orden de ejecución
        // 1. Primero crear los permisos
        // 2. Luego crear los roles y asignarles permisos
        // 3. Finalmente crear catálogos y usuarios

        $this->command->info('📋 Paso 1/3: Creando permisos del sistema...');
        $this->call([
            PermisosSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('👥 Paso 2/3: Creando roles y asignando permisos...');
        $this->call([
            RolesSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🗂️  Paso 3/3: Creando catálogos básicos y usuario admin...');
        $this->call([
            TipoProgramaSeeder::class,
            EstadoEstudianteSeeder::class,
            AdminUserSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🌍 Creando datos de ubicación...');
        $this->call([
            UbicacionSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🏛️  Creando instituciones y convenios...');
        $this->call([
            InstitucionSeeder::class,
            ConvenioSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('📚 Creando programas académicos...');
        $this->call([
            ProgramaAcademicoSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('👨‍🏫 Creando docentes...');
        $this->call([
            DocenteSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('👨‍🎓 Creando estudiantes...');
        $this->call([
            EstudianteSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('⏰ Creando horarios...');
        $this->call([
            HorarioSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('👥 Creando grupos...');
        $this->call([
            GrupoSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('📝 Creando inscripciones y pagos...');
        $this->call([
            InscripcionSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('📄 Creando documentos...');
        $this->call([
            DocumentoSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('🔔 Creando notificaciones...');
        $this->call([
            NotificacionSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('📋 Creando bitácora...');
        $this->call([
            BitacoraSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('   ✅ TODOS LOS SEEDERS EJECUTADOS EXITOSAMENTE');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('');
    }
}
