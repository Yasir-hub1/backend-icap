<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'titulo',
        'mensaje',
        'tipo',
        'leida',
        'usuario_id',
        'usuario_tipo', // 'student', 'teacher', 'admin'
        'datos_adicionales',
        'fecha_envio',
        'fecha_lectura'
    ];

    protected $casts = [
        'leida' => 'boolean',
        'datos_adicionales' => 'array',
        'fecha_envio' => 'datetime',
        'fecha_lectura' => 'datetime'
    ];

    // Relaciones
    public function usuario()
    {
        if ($this->usuario_tipo === 'student') {
            return $this->belongsTo(Estudiante::class, 'usuario_id');
        } elseif ($this->usuario_tipo === 'teacher' || $this->usuario_tipo === 'admin') {
            return $this->belongsTo(Docente::class, 'usuario_id');
        }

        return null;
    }

    // Scopes
    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorUsuario($query, $usuarioId, $usuarioTipo)
    {
        return $query->where('usuario_id', $usuarioId)
                    ->where('usuario_tipo', $usuarioTipo);
    }

    public function scopeRecientes($query, $dias = 7)
    {
        return $query->where('fecha_envio', '>=', now()->subDays($dias));
    }

    // Métodos
    public function marcarComoLeida()
    {
        $this->update([
            'leida' => true,
            'fecha_lectura' => now()
        ]);
    }

    public function getTipoColorAttribute()
    {
        $colores = [
            'info' => 'blue',
            'success' => 'green',
            'warning' => 'yellow',
            'error' => 'red',
            'documento' => 'purple',
            'pago' => 'green',
            'academico' => 'blue',
            'sistema' => 'gray'
        ];

        return $colores[$this->tipo] ?? 'gray';
    }

    public function getTipoIconoAttribute()
    {
        $iconos = [
            'info' => 'mdi-information',
            'success' => 'mdi-check-circle',
            'warning' => 'mdi-alert',
            'error' => 'mdi-alert-circle',
            'documento' => 'mdi-file-document',
            'pago' => 'mdi-credit-card',
            'academico' => 'mdi-school',
            'sistema' => 'mdi-cog'
        ];

        return $iconos[$this->tipo] ?? 'mdi-bell';
    }

    // Métodos estáticos para crear notificaciones
    public static function crearNotificacion($usuarioId, $usuarioTipo, $titulo, $mensaje, $tipo = 'info', $datosAdicionales = null)
    {
        return self::create([
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'leida' => false,
            'usuario_id' => $usuarioId,
            'usuario_tipo' => $usuarioTipo,
            'datos_adicionales' => $datosAdicionales,
            'fecha_envio' => now()
        ]);
    }

    public static function notificarDocumentoSubido($estudianteId, $documentoNombre)
    {
        return self::crearNotificacion(
            $estudianteId,
            'student',
            'Documento Subido',
            "Tu documento '{$documentoNombre}' ha sido subido exitosamente y está pendiente de validación.",
            'documento',
            ['documento' => $documentoNombre]
        );
    }

    public static function notificarDocumentoValidado($estudianteId, $documentoNombre, $aprobado)
    {
        $titulo = $aprobado ? 'Documento Aprobado' : 'Documento Rechazado';
        $mensaje = $aprobado
            ? "Tu documento '{$documentoNombre}' ha sido aprobado."
            : "Tu documento '{$documentoNombre}' ha sido rechazado. Por favor, revisa los comentarios y vuelve a subirlo.";

        return self::crearNotificacion(
            $estudianteId,
            'student',
            $titulo,
            $mensaje,
            $aprobado ? 'success' : 'error',
            ['documento' => $documentoNombre, 'aprobado' => $aprobado]
        );
    }

    public static function notificarPagoRecibido($estudianteId, $monto, $concepto)
    {
        return self::crearNotificacion(
            $estudianteId,
            'student',
            'Pago Recibido',
            "Hemos recibido tu pago de {$monto} BOB por concepto de {$concepto}.",
            'pago',
            ['monto' => $monto, 'concepto' => $concepto]
        );
    }

    public static function notificarPagoVerificado($estudianteId, $monto, $verificado)
    {
        $titulo = $verificado ? 'Pago Verificado' : 'Pago Rechazado';
        $mensaje = $verificado
            ? "Tu pago de {$monto} BOB ha sido verificado y aplicado a tu cuenta."
            : "Tu pago de {$monto} BOB ha sido rechazado. Por favor, contacta con administración.";

        return self::crearNotificacion(
            $estudianteId,
            'student',
            $titulo,
            $mensaje,
            $verificado ? 'success' : 'error',
            ['monto' => $monto, 'verificado' => $verificado]
        );
    }

    public static function notificarInscripcionAprobada($estudianteId, $programaNombre)
    {
        return self::crearNotificacion(
            $estudianteId,
            'student',
            'Inscripción Aprobada',
            "Tu inscripción al programa '{$programaNombre}' ha sido aprobada. ¡Bienvenido!",
            'academico',
            ['programa' => $programaNombre]
        );
    }

    public static function notificarNotaRegistrada($estudianteId, $materia, $nota)
    {
        return self::crearNotificacion(
            $estudianteId,
            'student',
            'Nueva Nota',
            "Se ha registrado tu nota de {$nota} en {$materia}.",
            'academico',
            ['materia' => $materia, 'nota' => $nota]
        );
    }

    public static function notificarCertificadoDisponible($estudianteId, $programaNombre)
    {
        return self::crearNotificacion(
            $estudianteId,
            'student',
            'Certificado Disponible',
            "Tu certificado del programa '{$programaNombre}' está disponible para descarga.",
            'success',
            ['programa' => $programaNombre]
        );
    }

    public static function notificarSistema($usuarioId, $usuarioTipo, $titulo, $mensaje)
    {
        return self::crearNotificacion(
            $usuarioId,
            $usuarioTipo,
            $titulo,
            $mensaje,
            'sistema'
        );
    }
}
