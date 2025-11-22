<?php

namespace App\Traits;

use App\Models\Notificacion;
use App\Models\Estudiante;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;

trait EnviaNotificaciones
{
    /**
     * Envía notificación a un estudiante
     *
     * @param int $estudianteId ID del estudiante (registro_estudiante)
     * @param string $titulo
     * @param string $mensaje
     * @param string $tipo
     * @param array|null $datosAdicionales
     * @return Notificacion|null
     */
    protected function notificarEstudiante(int $estudianteId, string $titulo, string $mensaje, string $tipo = 'info', ?array $datosAdicionales = null): ?Notificacion
    {
        try {
            return Notificacion::crearNotificacion(
                $estudianteId,
                'student',
                $titulo,
                $mensaje,
                $tipo,
                $datosAdicionales
            );
        } catch (\Exception $e) {
            Log::error("Error al enviar notificación a estudiante {$estudianteId}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Envía notificación a un admin/docente
     *
     * @param int $usuarioId ID del usuario (usuario_id)
     * @param string $titulo
     * @param string $mensaje
     * @param string $tipo
     * @param string $usuarioTipo 'admin' o 'teacher'
     * @param array|null $datosAdicionales
     * @return Notificacion|null
     */
    protected function notificarAdmin(int $usuarioId, string $titulo, string $mensaje, string $tipo = 'info', string $usuarioTipo = 'admin', ?array $datosAdicionales = null): ?Notificacion
    {
        try {
            return Notificacion::crearNotificacion(
                $usuarioId,
                $usuarioTipo,
                $titulo,
                $mensaje,
                $tipo,
                $datosAdicionales
            );
        } catch (\Exception $e) {
            Log::error("Error al enviar notificación a admin/docente {$usuarioId}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Notifica a todos los administradores
     *
     * @param string $titulo
     * @param string $mensaje
     * @param string $tipo
     * @param array|null $datosAdicionales
     * @return int Número de notificaciones enviadas
     */
    protected function notificarTodosAdmins(string $titulo, string $mensaje, string $tipo = 'info', ?array $datosAdicionales = null): int
    {
        try {
            $usuarios = Usuario::whereHas('rol', function ($query) {
                $query->where('nombre_rol', 'ADMIN');
            })->get();

            $enviadas = 0;
            foreach ($usuarios as $usuario) {
                $this->notificarAdmin($usuario->usuario_id, $titulo, $mensaje, $tipo, 'admin', $datosAdicionales);
                $enviadas++;
            }

            return $enviadas;
        } catch (\Exception $e) {
            Log::error("Error al enviar notificaciones a todos los admins: {$e->getMessage()}");
            return 0;
        }
    }

    // ========== NOTIFICACIONES ESPECÍFICAS ==========

    /**
     * Notifica nueva inscripción creada
     */
    protected function notificarNuevaInscripcion(Estudiante $estudiante, $programaNombre, $inscripcionId): void
    {
        // Notificar al estudiante
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Inscripción Creada',
            "Tu inscripción al programa '{$programaNombre}' ha sido registrada exitosamente. Está pendiente de revisión.",
            'academico',
            ['inscripcion_id' => $inscripcionId, 'programa' => $programaNombre]
        );

        // Notificar a todos los admins
        $this->notificarTodosAdmins(
            'Nueva Inscripción',
            "El estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) se ha inscrito en el programa '{$programaNombre}'.",
            'academico',
            ['inscripcion_id' => $inscripcionId, 'estudiante_id' => $estudiante->registro_estudiante, 'programa' => $programaNombre]
        );
    }

    /**
     * Notifica inscripción aprobada
     */
    protected function notificarInscripcionAprobada(Estudiante $estudiante, $programaNombre, $inscripcionId): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Inscripción Aprobada',
            "¡Felicidades! Tu inscripción al programa '{$programaNombre}' ha sido aprobada. Ya puedes acceder a los materiales del curso.",
            'success',
            ['inscripcion_id' => $inscripcionId, 'programa' => $programaNombre]
        );
    }

    /**
     * Notifica plan de pago creado
     */
    protected function notificarPlanPagoCreado(Estudiante $estudiante, $montoTotal, $totalCuotas, $planPagoId): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Plan de Pago Creado',
            "Se ha creado tu plan de pago por un monto total de {$montoTotal} BOB en {$totalCuotas} cuota(s). Revisa las fechas de vencimiento.",
            'pago',
            ['plan_pago_id' => $planPagoId, 'monto_total' => $montoTotal, 'total_cuotas' => $totalCuotas]
        );
    }

    /**
     * Notifica pago registrado/verificado
     */
    protected function notificarPagoRegistrado(Estudiante $estudiante, $monto, $concepto, $pagoId, $verificado = false): void
    {
        $titulo = $verificado ? 'Pago Verificado' : 'Pago Registrado';
        $mensaje = $verificado
            ? "Tu pago de {$monto} BOB por concepto de {$concepto} ha sido verificado y aplicado a tu cuenta."
            : "Hemos registrado tu pago de {$monto} BOB por concepto de {$concepto}. Está pendiente de verificación.";

        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            $titulo,
            $mensaje,
            $verificado ? 'success' : 'info',
            ['pago_id' => $pagoId, 'monto' => $monto, 'concepto' => $concepto, 'verificado' => $verificado]
        );
    }

    /**
     * Notifica pago rechazado
     */
    protected function notificarPagoRechazado(Estudiante $estudiante, $monto, $motivo, $pagoId): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Pago Rechazado',
            "Tu pago de {$monto} BOB ha sido rechazado. Motivo: {$motivo}. Por favor, contacta con administración.",
            'error',
            ['pago_id' => $pagoId, 'monto' => $monto, 'motivo' => $motivo]
        );
    }

    /**
     * Notifica cuota próxima a vencer
     */
    protected function notificarCuotaProximaVencer(Estudiante $estudiante, $monto, $fechaVencimiento, $cuotaId): void
    {
        $fechaFormateada = \Carbon\Carbon::parse($fechaVencimiento)->format('d/m/Y');
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Cuota Próxima a Vencer',
            "Tienes una cuota de {$monto} BOB que vence el {$fechaFormateada}. Por favor, realiza el pago a tiempo.",
            'warning',
            ['cuota_id' => $cuotaId, 'monto' => $monto, 'fecha_vencimiento' => $fechaVencimiento]
        );
    }

    /**
     * Notifica cuota vencida
     */
    protected function notificarCuotaVencida(Estudiante $estudiante, $monto, $diasVencida, $cuotaId): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Cuota Vencida',
            "Tienes una cuota de {$monto} BOB vencida hace {$diasVencida} día(s). Por favor, realiza el pago lo antes posible para evitar penalizaciones.",
            'error',
            ['cuota_id' => $cuotaId, 'monto' => $monto, 'dias_vencida' => $diasVencida]
        );
    }

    /**
     * Notifica plan de pago completado
     */
    protected function notificarPlanPagoCompletado(Estudiante $estudiante, $montoTotal, $planPagoId): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Plan de Pago Completado',
            "¡Felicidades! Has completado el pago de tu plan de pago por un total de {$montoTotal} BOB. Gracias por tu puntualidad.",
            'success',
            ['plan_pago_id' => $planPagoId, 'monto_total' => $montoTotal]
        );
    }

    /**
     * Notifica documento aprobado
     */
    protected function notificarDocumentoAprobado(Estudiante $estudiante, $documentoNombre): void
    {
        Notificacion::notificarDocumentoValidado($estudiante->registro_estudiante, $documentoNombre, true);
    }

    /**
     * Notifica documento rechazado
     */
    protected function notificarDocumentoRechazado(Estudiante $estudiante, $documentoNombre, $motivo): void
    {
        Notificacion::notificarDocumentoValidado($estudiante->registro_estudiante, $documentoNombre, false);
    }

    /**
     * Notifica que todos los documentos fueron aprobados
     */
    protected function notificarDocumentosCompletos(Estudiante $estudiante): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Documentos Completados',
            "¡Excelente! Todos tus documentos han sido aprobados. Ya puedes realizar inscripciones a los programas disponibles.",
            'success',
            ['estudiante_id' => $estudiante->registro_estudiante]
        );
    }

    /**
     * Notifica cambio de estado del estudiante
     */
    protected function notificarCambioEstadoEstudiante(Estudiante $estudiante, $estadoAnterior, $estadoNuevo): void
    {
        $estadoNuevoNombre = $estudiante->estadoEstudiante->nombre ?? 'Nuevo Estado';
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Cambio de Estado',
            "Tu estado ha cambiado a: {$estadoNuevoNombre}.",
            'info',
            ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => $estadoNuevo]
        );
    }

    /**
     * Notifica activación de estudiante
     */
    protected function notificarEstudianteActivado(Estudiante $estudiante): void
    {
        $this->notificarEstudiante(
            $estudiante->registro_estudiante,
            'Cuenta Activada',
            "Tu cuenta ha sido activada. Ya puedes acceder a todos los servicios del sistema.",
            'success',
            ['estudiante_id' => $estudiante->registro_estudiante]
        );
    }

    /**
     * Notifica documentos pendientes de validar (a admins)
     */
    protected function notificarDocumentosPendientes(int $adminId, Estudiante $estudiante, int $cantidadDocumentos): void
    {
        $this->notificarAdmin(
            $adminId,
            'Documentos Pendientes de Validar',
            "El estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) tiene {$cantidadDocumentos} documento(s) pendiente(s) de validación.",
            'warning',
            'admin',
            ['estudiante_id' => $estudiante->registro_estudiante, 'cantidad_documentos' => $cantidadDocumentos]
        );
    }

    /**
     * Notifica pagos pendientes de verificar (a admins)
     */
    protected function notificarPagosPendientes(int $adminId, Estudiante $estudiante, int $cantidadPagos): void
    {
        $this->notificarAdmin(
            $adminId,
            'Pagos Pendientes de Verificar',
            "El estudiante {$estudiante->nombre} {$estudiante->apellido} (CI: {$estudiante->ci}) tiene {$cantidadPagos} pago(s) pendiente(s) de verificación.",
            'warning',
            'admin',
            ['estudiante_id' => $estudiante->registro_estudiante, 'cantidad_pagos' => $cantidadPagos]
        );
    }
}

