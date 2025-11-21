<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InscripcionSeeder extends Seeder
{
    public function run(): void
    {
        // Inscripciones
        // Verificar si la migración ya se ejecutó (usa estudiante_id) o no (usa estudiante_registro)
        $columnExists = DB::selectOne(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'inscripcion' AND column_name = 'estudiante_id'"
        );

        // Verificar si la migración de plan_pago ya se ejecutó (usa inscripcion_id) o no (usa plan_pago_id)
        $planPagoColumnExists = DB::selectOne(
            "SELECT column_name FROM information_schema.columns WHERE table_name = 'plan_pago' AND column_name = 'inscripcion_id'"
        );

        if ($columnExists) {
            // La migración ya se ejecutó, usar estudiante_id
            $estudianteIds = DB::table('estudiante')->orderBy('id')->limit(3)->pluck('id')->toArray();

            if (count($estudianteIds) < 3) {
                throw new \Exception('Se necesitan al menos 3 estudiantes creados antes de ejecutar InscripcionSeeder');
            }

            $inscripciones = [
                [
                    'fecha' => '2025-01-15',
                    'programa_id' => 1,
                    'estudiante_id' => $estudianteIds[0],
                ],
                [
                    'fecha' => '2025-01-20',
                    'programa_id' => 2,
                    'estudiante_id' => $estudianteIds[1],
                ],
                [
                    'fecha' => '2025-02-01',
                    'programa_id' => 3,
                    'estudiante_id' => $estudianteIds[2],
                ],
            ];
        } else {
            // La migración no se ejecutó, usar estudiante_registro
            $estudianteRegistros = DB::table('estudiante')->orderBy('id')->limit(3)->pluck('registro_estudiante')->toArray();

            if (count($estudianteRegistros) < 3) {
                throw new \Exception('Se necesitan al menos 3 estudiantes creados antes de ejecutar InscripcionSeeder');
            }

            $inscripciones = [
                [
                    'fecha' => '2025-01-15',
                    'programa_id' => 1,
                    'estudiante_registro' => $estudianteRegistros[0],
                ],
                [
                    'fecha' => '2025-01-20',
                    'programa_id' => 2,
                    'estudiante_registro' => $estudianteRegistros[1],
                ],
                [
                    'fecha' => '2025-02-01',
                    'programa_id' => 3,
                    'estudiante_registro' => $estudianteRegistros[2],
                ],
            ];
        }
        $inscripcionIds = [];
        foreach ($inscripciones as $inscripcion) {
            $id = DB::table('inscripcion')->insertGetId($inscripcion);
            $inscripcionIds[] = $id;
        }

        // Planes de Pago (ahora con inscripcion_id)
        if ($planPagoColumnExists) {
            // La migración ya se ejecutó, usar inscripcion_id
            $planesPago = [
                [
                    'inscripcion_id' => $inscripcionIds[0],
                    'total_cuotas' => 6,
                    'monto_total' => 15000.00,
                ],
                [
                    'inscripcion_id' => $inscripcionIds[1],
                    'total_cuotas' => 4,
                    'monto_total' => 12000.00,
                ],
                [
                    'inscripcion_id' => $inscripcionIds[2],
                    'total_cuotas' => 3,
                    'monto_total' => 8000.00,
                ],
            ];
        } else {
            // La migración no se ejecutó, crear planes sin inscripcion_id (se asociarán después)
            $planesPago = [
                [
                    'total_cuotas' => 6,
                    'monto_total' => 15000.00,
                ],
                [
                    'total_cuotas' => 4,
                    'monto_total' => 12000.00,
                ],
                [
                    'total_cuotas' => 3,
                    'monto_total' => 8000.00,
                ],
            ];
        }
        $planPagoIds = [];
        foreach ($planesPago as $plan) {
            $id = DB::table('plan_pago')->insertGetId($plan);
            $planPagoIds[] = $id;
        }

        // Cuotas
        $cuotas = [
            // Cuotas para plan 1 (6 cuotas de 2500)
            ['fecha_ini' => '2025-02-01', 'fecha_fin' => '2025-02-28', 'monto' => 2500.00, 'plan_pago_id' => $planPagoIds[0]],
            ['fecha_ini' => '2025-03-01', 'fecha_fin' => '2025-03-31', 'monto' => 2500.00, 'plan_pago_id' => $planPagoIds[0]],
            ['fecha_ini' => '2025-04-01', 'fecha_fin' => '2025-04-30', 'monto' => 2500.00, 'plan_pago_id' => $planPagoIds[0]],
            // Cuotas para plan 2 (4 cuotas de 3000)
            ['fecha_ini' => '2025-02-01', 'fecha_fin' => '2025-02-28', 'monto' => 3000.00, 'plan_pago_id' => $planPagoIds[1]],
            ['fecha_ini' => '2025-03-01', 'fecha_fin' => '2025-03-31', 'monto' => 3000.00, 'plan_pago_id' => $planPagoIds[1]],
            // Cuotas para plan 3 (3 cuotas de 2666.67)
            ['fecha_ini' => '2025-02-01', 'fecha_fin' => '2025-02-28', 'monto' => 2666.67, 'plan_pago_id' => $planPagoIds[2]],
            ['fecha_ini' => '2025-03-01', 'fecha_fin' => '2025-03-31', 'monto' => 2666.67, 'plan_pago_id' => $planPagoIds[2]],
        ];
        $cuotaIds = [];
        foreach ($cuotas as $cuota) {
            $id = DB::table('cuotas')->insertGetId($cuota);
            $cuotaIds[] = $id;
        }

        // Pagos
        $pagos = [
            [
                'fecha' => '2025-02-05',
                'monto' => 2500.00,
                'token' => null,
                'cuota_id' => $cuotaIds[0],
                'verificado' => true,
                'fecha_verificacion' => '2025-02-05 10:30:00',
                'verificado_por' => 1,
                'observaciones' => 'Pago verificado correctamente',
                'metodo' => 'Transferencia',
            ],
            [
                'fecha' => '2025-03-05',
                'monto' => 2500.00,
                'token' => null,
                'cuota_id' => $cuotaIds[1],
                'verificado' => true,
                'fecha_verificacion' => '2025-03-05 11:00:00',
                'verificado_por' => 1,
                'observaciones' => 'Pago en efectivo verificado',
                'metodo' => 'Efectivo',
            ],
            [
                'fecha' => '2025-02-10',
                'monto' => 3000.00,
                'token' => null,
                'cuota_id' => $cuotaIds[3],
                'verificado' => false,
                'fecha_verificacion' => null,
                'verificado_por' => null,
                'observaciones' => 'Pago pendiente de verificación',
                'metodo' => 'Tarjeta',
            ],
        ];
        DB::table('pagos')->insert($pagos);

        // Descuentos
        $descuentos = [
            [
                'nombre' => 'Beca Excelencia',
                'descuento' => 10.00,
                'inscripcion_id' => 1,
            ],
            [
                'nombre' => 'Pronto Pago',
                'descuento' => 5.00,
                'inscripcion_id' => 2,
            ],
        ];
        DB::table('descuento')->insert($descuentos);
    }
}

