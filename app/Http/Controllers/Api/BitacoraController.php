<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BitacoraController extends Controller
{
    /**
     * Listar registros de bitácora
     */
    public function index(Request $request): JsonResponse
    {
        $query = Bitacora::with(['usuario:id,ci,nombre,apellido']);

        if ($request->filled('usuario_id')) {
            $query->where('usuario_id', $request->get('usuario_id'));
        }

        if ($request->filled('tabla')) {
            $query->where('tabla', $request->get('tabla'));
        }

        if ($request->filled('transaccion')) {
            $query->where('transaccion', 'ILIKE', '%' . $request->get('transaccion') . '%');
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->get('fecha_hasta'));
        }

        if ($request->filled('recientes')) {
            $query->recientes();
        }

        $perPage = $request->get('per_page', 50);
        $bitacora = $query->orderBy('fecha', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bitacora,
            'message' => 'Registros de bitácora obtenidos exitosamente'
        ]);
    }

    /**
     * Obtener registro de bitácora específico
     */
    public function show(int $id): JsonResponse
    {
        $registro = Bitacora::with(['usuario:id,ci,nombre,apellido'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $registro,
            'message' => 'Registro de bitácora obtenido exitosamente'
        ]);
    }

    /**
     * Crear nuevo registro de bitácora
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'tabla' => 'required|string|max:100',
            'codTable' => 'nullable|string',
            'transaccion' => 'required|string|max:200',
            'Usuario_id' => 'required|exists:usuario,id'
        ]);

        $registro = Bitacora::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $registro->load('usuario'),
            'message' => 'Registro de bitácora creado exitosamente'
        ], 201);
    }

    /**
     * Obtener estadísticas de bitácora
     */
    public function estadisticas(Request $request): JsonResponse
    {
        $query = Bitacora::query();

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->get('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->get('fecha_hasta'));
        }

        $estadisticas = [
            'total_registros' => $query->count(),
            'por_tabla' => $query->select('tabla', DB::raw('count(*) as total'))
                ->groupBy('tabla')
                ->orderBy('total', 'desc')
                ->get(),
            'por_usuario' => $query->select('usuario_id', DB::raw('count(*) as total'))
                ->with('usuario:id,ci,nombre,apellido')
                ->groupBy('usuario_id')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get(),
            'por_transaccion' => $query->select('transaccion', DB::raw('count(*) as total'))
                ->groupBy('transaccion')
                ->orderBy('total', 'desc')
                ->limit(10)
                ->get(),
            'por_dia' => $query->select(DB::raw('DATE(fecha) as fecha'), DB::raw('count(*) as total'))
                ->groupBy(DB::raw('DATE(fecha)'))
                ->orderBy('fecha', 'desc')
                ->limit(30)
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $estadisticas,
            'message' => 'Estadísticas de bitácora obtenidas exitosamente'
        ]);
    }

    /**
     * Limpiar registros antiguos de bitácora
     */
    public function limpiar(Request $request): JsonResponse
    {
        $request->validate([
            'dias_antiguedad' => 'required|integer|min:30|max:365'
        ]);

        $dias = $request->get('dias_antiguedad');
        $fechaLimite = now()->subDays($dias);

        $eliminados = Bitacora::where('fecha', '<', $fechaLimite->toDateString())->delete();

        return response()->json([
            'success' => true,
            'data' => ['eliminados' => $eliminados],
            'message' => "Se eliminaron {$eliminados} registros de bitácora anteriores a {$fechaLimite->format('Y-m-d')}"
        ]);
    }
}
