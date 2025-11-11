<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Controller para tipos de mantenimiento
 */
class MaintenanceTypeController extends Controller
{
    /**
     * Listar tipos de mantenimiento disponibles
     *
     * GET /api/v1/maintenance-types?vehicle_id=37
     *
     * Query params:
     * - vehicle_id (opcional): ID del vehículo para filtrar por tipo_valor_trabajo y marca
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        try {
            $vehicleId = request()->query('vehicle_id');
            $vehicleBrand = null;
            $tipoValorTrabajo = null;

            // Si se proporciona vehicle_id, filtrar por tipo_valor_trabajo o marca
            if ($vehicleId) {
                $vehicle = \App\Models\Vehicle::find($vehicleId);

                if ($vehicle) {
                    $vehicleBrand = $vehicle->brand;
                    $tipoValorTrabajo = $vehicle->tipo_valor_trabajo;

                    Log::info('[API] Vehículo encontrado para filtrar mantenimientos', [
                        'vehicle_id' => $vehicleId,
                        'brand' => $vehicleBrand,
                        'tipo_valor_trabajo' => $tipoValorTrabajo,
                    ]);

                    // Intentar obtener mantenimientos específicos por tipo_valor_trabajo
                    if (!empty($tipoValorTrabajo)) {
                        $modelMaintenances = \App\Models\ModelMaintenance::where('tipo_valor_trabajo', $tipoValorTrabajo)
                            ->with('maintenanceType')
                            ->get();

                        if ($modelMaintenances->isNotEmpty()) {
                            $maintenanceTypes = $modelMaintenances->map(function ($mm) {
                                return $mm->maintenanceType;
                            })->filter()->unique('id');

                            Log::info('[API] Usando mantenimientos específicos por tipo_valor_trabajo', [
                                'tipo_valor_trabajo' => $tipoValorTrabajo,
                                'total' => $maintenanceTypes->count(),
                            ]);

                            return $this->formatResponse($maintenanceTypes, $vehicleId, $vehicleBrand, $tipoValorTrabajo);
                        }
                    }

                    // Si no hay tipo_valor_trabajo o no tiene mantenimientos, filtrar por marca
                    if (!empty($vehicleBrand)) {
                        $maintenanceTypes = MaintenanceType::where('is_active', true)
                            ->where('brand', $vehicleBrand)
                            ->orderBy('kilometers', 'asc')
                            ->get();

                        Log::info('[API] Usando mantenimientos filtrados por marca', [
                            'brand' => $vehicleBrand,
                            'total' => $maintenanceTypes->count(),
                        ]);

                        return $this->formatResponse($maintenanceTypes, $vehicleId, $vehicleBrand, null);
                    }
                }
            }

            // Si no se proporciona vehicle_id o no se pudo filtrar, devolver todos
            $maintenanceTypes = MaintenanceType::where('is_active', true)
                ->orderBy('kilometers', 'asc')
                ->get();

            Log::info('[API] Listando todos los tipos de mantenimiento', [
                'total' => $maintenanceTypes->count(),
            ]);

            return $this->formatResponse($maintenanceTypes, $vehicleId, $vehicleBrand, $tipoValorTrabajo);

        } catch (\Exception $e) {
            Log::error('[API] Error listando tipos de mantenimiento', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Formatear respuesta de tipos de mantenimiento
     */
    private function formatResponse($maintenanceTypes, $vehicleId, $vehicleBrand, $tipoValorTrabajo): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $maintenanceTypes->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'kilometers' => $type->kilometers,
                    'estimated_duration_minutes' => $this->estimateDuration($type->kilometers),
                    'description' => $type->description,
                    'brand' => $type->brand ?? null,
                    'is_active' => $type->is_active,
                ];
            }),
            'filters' => [
                'vehicle_id' => $vehicleId,
                'brand' => $vehicleBrand,
                'tipo_valor_trabajo' => $tipoValorTrabajo,
            ],
        ]);
    }

    /**
     * Estimar duración del mantenimiento basado en kilómetros
     */
    private function estimateDuration(int $kilometers): int
    {
        $durations = [
            5000 => 45,
            10000 => 60,
            15000 => 90,
            20000 => 90,
            30000 => 120,
            40000 => 120,
        ];

        return $durations[$kilometers] ?? 60;
    }
}
