<?php

namespace App\Traits;

use App\Models\Bitacora;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait RegistraBitacora
{
    /**
     * Registrar acción en la bitácora
     * 
     * @param string $tabla Nombre de la tabla afectada
     * @param int|null $codTabla ID del registro afectado
     * @param string $transaccion Descripción de la acción (CREAR, EDITAR, ELIMINAR, VER, etc.)
     * @param int|null $usuarioId ID del usuario que realiza la acción (opcional, se obtiene del JWT si no se proporciona)
     * @return bool
     */
    protected function registrarBitacora(string $tabla, ?int $codTabla, string $transaccion, ?int $usuarioId = null): bool
    {
        try {
            // Obtener usuario del JWT si no se proporciona
            if ($usuarioId === null) {
                $usuarioId = $this->obtenerUsuarioId();
            }

            // Si no hay usuario, no registrar
            if ($usuarioId === null) {
                Log::warning('Bitácora: No se pudo obtener el ID del usuario');
                return false;
            }

            Bitacora::create([
                'fecha' => now(),
                'tabla' => $tabla,
                'codTabla' => $codTabla,
                'transaccion' => $transaccion,
                'usuario_id' => $usuarioId
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error al registrar bitácora: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener el ID del usuario autenticado desde el JWT
     * 
     * @return int|null
     */
    protected function obtenerUsuarioId(): ?int
    {
        try {
            // Intentar obtener del usuario autenticado
            $user = Auth::user();
            if ($user && isset($user->usuario_id)) {
                return $user->usuario_id;
            }

            // Intentar obtener del JWT payload
            $payload = Auth::payload();
            if ($payload && isset($payload['usuario_id'])) {
                return (int) $payload['usuario_id'];
            }

            // Intentar obtener del sub (subject) del JWT
            if ($payload && isset($payload['sub'])) {
                return (int) $payload['sub'];
            }

            return null;
        } catch (\Exception $e) {
            Log::error('Error al obtener usuario ID para bitácora: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Registrar acción de creación
     */
    protected function registrarCreacion(string $tabla, int $codTabla, string $descripcion = ''): bool
    {
        $transaccion = 'CREAR' . ($descripcion ? " - {$descripcion}" : '');
        return $this->registrarBitacora($tabla, $codTabla, $transaccion);
    }

    /**
     * Registrar acción de edición
     */
    protected function registrarEdicion(string $tabla, int $codTabla, string $descripcion = ''): bool
    {
        $transaccion = 'EDITAR' . ($descripcion ? " - {$descripcion}" : '');
        return $this->registrarBitacora($tabla, $codTabla, $transaccion);
    }

    /**
     * Registrar acción de eliminación
     */
    protected function registrarEliminacion(string $tabla, int $codTabla, string $descripcion = ''): bool
    {
        $transaccion = 'ELIMINAR' . ($descripcion ? " - {$descripcion}" : '');
        return $this->registrarBitacora($tabla, $codTabla, $transaccion);
    }

    /**
     * Registrar acción de visualización
     */
    protected function registrarVisualizacion(string $tabla, ?int $codTabla = null, string $descripcion = ''): bool
    {
        $transaccion = 'VER' . ($descripcion ? " - {$descripcion}" : '');
        return $this->registrarBitacora($tabla, $codTabla, $transaccion);
    }

    /**
     * Registrar acción personalizada
     */
    protected function registrarAccion(string $tabla, ?int $codTabla, string $accion, string $descripcion = ''): bool
    {
        $transaccion = strtoupper($accion) . ($descripcion ? " - {$descripcion}" : '');
        return $this->registrarBitacora($tabla, $codTabla, $transaccion);
    }
}

