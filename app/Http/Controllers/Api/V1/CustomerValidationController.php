<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\VehiculoSoapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para validación de clientes y vehículos
 */
class CustomerValidationController extends Controller
{
    protected VehiculoSoapService $vehiculoService;

    public function __construct(VehiculoSoapService $vehiculoService)
    {
        $this->vehiculoService = $vehiculoService;
    }

    /**
     * Validar DNI/RUC y placa, retornar datos del cliente y vehículo
     *
     * POST /api/v1/customers/validate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_number' => 'required|string|max:20',
            'license_plate' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de validación inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        $documentNumber = trim($request->document_number);
        $licensePlate = strtoupper(trim($request->license_plate));

        Log::info('[API] Validando cliente y vehículo', [
            'document_number' => $documentNumber,
            'license_plate' => $licensePlate,
        ]);

        try {
            // 1. Buscar usuario en BD local
            $user = User::where('document_number', $documentNumber)->first();

            if (!$user) {
                Log::warning('[API] Usuario no encontrado en BD local', [
                    'document_number' => $documentNumber,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Cliente no encontrado',
                    'message' => 'No se encontró un cliente con el DNI/RUC proporcionado'
                ], 404);
            }

            // 2. Buscar vehículo en BD local
            $vehicle = Vehicle::where('license_plate', $licensePlate)->first();

            if (!$vehicle) {
                Log::warning('[API] Vehículo no encontrado en BD local', [
                    'license_plate' => $licensePlate,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Vehículo no encontrado',
                    'message' => 'No se encontró un vehículo con la placa proporcionada'
                ], 404);
            }

            // 3. Verificar que el vehículo pertenezca al usuario
            if ($vehicle->user_id && $vehicle->user_id !== $user->id) {
                Log::warning('[API] Vehículo no pertenece al usuario', [
                    'document_number' => $documentNumber,
                    'license_plate' => $licensePlate,
                    'vehicle_owner_id' => $vehicle->user_id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Vehículo no pertenece al cliente',
                    'message' => 'El vehículo no está registrado a nombre del cliente proporcionado'
                ], 403);
            }

            Log::info('[API] Validación exitosa', [
                'user_id' => $user->id,
                'vehicle_id' => $vehicle->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $user->id,
                        'name' => explode(' ', $user->name)[0] ?? $user->name,
                        'last_name' => implode(' ', array_slice(explode(' ', $user->name), 1)) ?: '',
                        'full_name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'document_number' => $user->document_number,
                    ],
                    'vehicle' => [
                        'id' => $vehicle->id,
                        'license_plate' => $vehicle->license_plate,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'brand_code' => $vehicle->brand_code,
                        'brand_name' => $vehicle->brand_name ?? $this->getBrandName($vehicle->brand_code),
                        'vehicle_id' => $vehicle->vehicle_id,
                    ]
                ],
                'message' => 'Cliente y vehículo validados exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error en validación', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => 'Ocurrió un error al validar los datos'
            ], 500);
        }
    }

    /**
     * Obtener todos los vehículos de un cliente
     *
     * GET /api/v1/customers/{document}/vehicles
     *
     * @param string $document
     * @return JsonResponse
     */
    public function getVehicles(string $document): JsonResponse
    {
        try {
            $user = User::where('document_number', $document)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente no encontrado',
                ], 404);
            }

            $vehicles = Vehicle::where('user_id', $user->id)->get();

            return response()->json([
                'success' => true,
                'data' => $vehicles->map(function ($vehicle) {
                    return [
                        'id' => $vehicle->id,
                        'license_plate' => $vehicle->license_plate,
                        'model' => $vehicle->model,
                        'year' => $vehicle->year,
                        'brand_code' => $vehicle->brand_code,
                        'brand_name' => $vehicle->brand_name ?? $this->getBrandName($vehicle->brand_code),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error obteniendo vehículos', [
                'document' => $document,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Helper para obtener nombre de marca
     */
    private function getBrandName(string $brandCode): string
    {
        $brands = [
            'Z01' => 'TOYOTA',
            'Z02' => 'LEXUS',
            'Z03' => 'HINO',
        ];

        return $brands[$brandCode] ?? 'DESCONOCIDA';
    }
}
