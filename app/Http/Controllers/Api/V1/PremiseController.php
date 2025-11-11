<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Local;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para gestión de locales/centros
 */
class PremiseController extends Controller
{
    /**
     * Listar todos los locales activos
     *
     * GET /api/v1/premises
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $premises = Local::where('is_active', true)
                ->orderBy('name', 'asc')
                ->get();

            Log::info('[API] Listando locales', [
                'total' => $premises->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $premises->map(function ($premise) {
                    return [
                        'id' => $premise->id,
                        'code' => $premise->code,
                        'name' => $premise->name,
                        'address' => $premise->address,
                        'phone' => $premise->phone,
                        'is_active' => $premise->is_active,
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error listando locales', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Obtener detalle de un local específico
     *
     * GET /api/v1/premises/{code}
     *
     * @param string $code
     * @return JsonResponse
     */
    public function show(string $code): JsonResponse
    {
        try {
            $premise = Local::where('code', $code)->first();

            if (!$premise) {
                return response()->json([
                    'success' => false,
                    'error' => 'Local no encontrado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $premise->id,
                    'code' => $premise->code,
                    'name' => $premise->name,
                    'address' => $premise->address,
                    'phone' => $premise->phone,
                    'email' => $premise->email,
                    'is_active' => $premise->is_active,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error obteniendo local', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
