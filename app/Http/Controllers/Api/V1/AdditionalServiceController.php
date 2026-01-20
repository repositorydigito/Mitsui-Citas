<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdditionalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para servicios adicionales
 */
class AdditionalServiceController extends Controller
{
    /**
     * Listar servicios adicionales disponibles
     *
     * GET /api/v1/additional-services?vehicle_id=37&center_code=M013
     *
     * Query params:
     * - vehicle_id (opcional): ID del vehículo para filtrar por marca
     * - center_code (opcional): Código del local para filtrar servicios disponibles
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $query = AdditionalService::where('is_active', true);

            // Filtrar por marca del vehículo si se proporciona vehicle_id
            $vehicleId = request()->query('vehicle_id');
            $vehicleBrand = null;

            if ($vehicleId) {
                $vehicle = \App\Models\Vehicle::find($vehicleId);
                if ($vehicle && !empty($vehicle->brand)) {
                    $vehicleBrand = strtoupper(trim($vehicle->brand));
                    $query->porMarca($vehicleBrand);

                    Log::info('[API] Filtrando servicios adicionales por marca', [
                        'vehicle_id' => $vehicleId,
                        'brand' => $vehicleBrand,
                    ]);
                }
            }

            // Filtrar por local si se proporciona center_code
            $centerCode = request()->query('center_code');

            if ($centerCode) {
                $query->where(function($q) use ($centerCode) {
                    $q->whereJsonContains('center_code', $centerCode)
                        ->orWhereNull('center_code')
                        ->orWhereJsonLength('center_code', 0);
                });

                Log::info('[API] Filtrando servicios adicionales por local', [
                    'center_code' => $centerCode,
                ]);
            }

            $services = $query->orderBy('name', 'asc')->get();

            Log::info('[API] Listando servicios adicionales', [
                'total' => $services->count(),
                'filtered_by_brand' => $vehicleBrand,
            ]);

            return response()->json([
                'success' => true,
                'data' => $services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'code' => $service->code,
                        'description' => $service->description,
                        'price' => $service->price,
                        'duration_minutes' => $service->duration_minutes,
                        'brand' => $service->brand,
                        'center_code' => $service->center_code,
                        'available_days' => $service->available_days,
                        'is_active' => $service->is_active,
                    ];
                }),
                'filters' => [
                    'vehicle_id' => $vehicleId,
                    'brand' => $vehicleBrand,
                    'center_code' => $centerCode ?? null,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error listando servicios adicionales', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
