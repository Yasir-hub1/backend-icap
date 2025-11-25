<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EstadoEstudiante;
use Illuminate\Support\Facades\DB;

class EstadoEstudianteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Crea los 5 estados de estudiante con IDs específicos para mantener consistencia
     * con la lógica del código:
     * - estado_id 1: Pre-registrado
     * - estado_id 2: Documentos incompletos
     * - estado_id 3: En revisión
     * - estado_id 4: Validado - Activo
     * - estado_id 5: Rechazado
     */
    public function run(): void
    {
        $estadosEstudiante = [
            [
                'id' => 1,
                'nombre_estado' => 'Pre-registrado',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 2,
                'nombre_estado' => 'Documentos incompletos',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 3,
                'nombre_estado' => 'En revisión',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 4,
                'nombre_estado' => 'Validado - Activo',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 5,
                'nombre_estado' => 'Rechazado',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // Usar DB::table para insertar/actualizar con IDs específicos
        // PostgreSQL requiere manejar la secuencia cuando insertamos IDs manualmente
        foreach ($estadosEstudiante as $estado) {
            // Verificar si el estado ya existe
            $existe = DB::table('estado_estudiante')->where('id', $estado['id'])->exists();

            if ($existe) {
                // Actualizar si existe
                DB::table('estado_estudiante')
                    ->where('id', $estado['id'])
                    ->update([
                        'nombre_estado' => $estado['nombre_estado'],
                        'updated_at' => $estado['updated_at']
                    ]);
                $this->command->info("✅ Estado ID {$estado['id']} ({$estado['nombre_estado']}) actualizado");
            } else {
                // Insertar si no existe (con ID específico)
                // Para PostgreSQL, necesitamos insertar directamente con el ID
                DB::statement("
                    INSERT INTO estado_estudiante (id, nombre_estado, created_at, updated_at)
                    VALUES (?, ?, ?, ?)
                ", [
                    $estado['id'],
                    $estado['nombre_estado'],
                    $estado['created_at'],
                    $estado['updated_at']
                ]);

                // Actualizar la secuencia de PostgreSQL para que el próximo auto-increment sea correcto
                $maxId = DB::table('estado_estudiante')->max('id');
                DB::statement("SELECT setval('estado_estudiante_id_seq', GREATEST(?, 1), true)", [$maxId]);

                $this->command->info("✅ Estado ID {$estado['id']} ({$estado['nombre_estado']}) creado");
            }
        }

        // También actualizar estados existentes que puedan tener nombres antiguos
        // Mapeo de nombres antiguos a nuevos IDs
        $mapeoNombres = [
            'pre-inscrito' => 1,
            'inscrito' => 2,
            'validado' => 4,
            'Rechazado' => 5
        ];

        foreach ($mapeoNombres as $nombreAntiguo => $nuevoId) {
            $estadoExistente = DB::table('estado_estudiante')
                ->where('nombre_estado', $nombreAntiguo)
                ->where('id', '!=', $nuevoId)
                ->first();

            if ($estadoExistente) {
                // Si hay estudiantes con este estado, actualizar sus referencias primero
                $estudiantesConEsteEstado = DB::table('estudiante')
                    ->where('Estado_id', $estadoExistente->id)
                    ->count();

                if ($estudiantesConEsteEstado > 0) {
                    // Actualizar referencias de estudiantes al nuevo ID
                    DB::table('estudiante')
                        ->where('Estado_id', $estadoExistente->id)
                        ->update(['Estado_id' => $nuevoId]);

                    $this->command->info("Actualizados {$estudiantesConEsteEstado} estudiantes del estado '{$nombreAntiguo}' (ID: {$estadoExistente->id}) al nuevo ID: {$nuevoId}");
                }

                // Eliminar el estado duplicado
                DB::table('estado_estudiante')
                    ->where('id', $estadoExistente->id)
                    ->delete();

                $this->command->info("Estado duplicado '{$nombreAntiguo}' (ID: {$estadoExistente->id}) eliminado");
            }
        }

        $this->command->info('✅ Estados de estudiante creados/actualizados exitosamente');
        $this->command->info('📋 Estados disponibles:');
        $estados = DB::table('estado_estudiante')->orderBy('id')->get();
        foreach ($estados as $estado) {
            $this->command->info("   - ID {$estado->id}: {$estado->nombre_estado}");
        }
    }
}
