<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCitaC4CJob;
use App\Jobs\ProcessAppointmentAfterCreationJob;
use App\Models\AdditionalService;
use App\Models\Appointment;
use App\Models\CenterOrganizationMapping;
use App\Models\Local;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Controller para gestión de citas
 *
 * IMPORTANTE: Este controller replica EXACTAMENTE la lógica de AgendarCita.php
 */
class AppointmentController extends Controller
{
    /**
     * Crear una nueva cita
     *
     * POST /api/v1/appointments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_document' => 'required|string',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
            'license_plate' => 'nullable|string',
            'premise_code' => 'required|string|exists:premises,code',
            'appointment_date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'maintenance_type' => 'nullable|string',
            'service_mode' => 'nullable|in:regular,express',
            'additional_services' => 'nullable|array',
            'additional_services.*' => 'integer|exists:additional_services,id',
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
            'comments' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de validación inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        DB::beginTransaction();

        try {
            Log::info('[API] Iniciando creación de cita', [
                'customer_document' => $request->customer_document,
                'premise_code' => $request->premise_code,
                'date' => $request->appointment_date,
            ]);

            // 1. VALIDAR USUARIO
            $user = User::where('document_number', $request->customer_document)->first();

            if (!$user) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Cliente no encontrado',
                ], 404);
            }

            // 2. VALIDAR Y OBTENER VEHÍCULO
            if ($request->vehicle_id) {
                $vehicle = Vehicle::find($request->vehicle_id);
            } elseif ($request->license_plate) {
                $vehicle = Vehicle::where('license_plate', strtoupper($request->license_plate))->first();
            } else {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Debe proporcionar vehicle_id o license_plate',
                ], 400);
            }

            if (!$vehicle) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Vehículo no encontrado',
                ], 404);
            }

            // 3. VALIDAR LOCAL
            $local = Local::where('code', $request->premise_code)->first();

            if (!$local) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'error' => 'Local no encontrado',
                ], 404);
            }

            // 4. ✅ VALIDACIÓN CRÍTICA: Mapeo organizacional
            if (!$vehicle->brand_code) {
                DB::rollBack();
                Log::error('[API] Vehículo sin brand_code', [
                    'vehicle_id' => $vehicle->id,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'El vehículo no tiene código de marca configurado',
                ], 422);
            }

            $mappingExists = CenterOrganizationMapping::mappingExists(
                $local->code,
                $vehicle->brand_code
            );

            if (!$mappingExists) {
                DB::rollBack();
                Log::error('[API] No existe mapeo organizacional', [
                    'center_code' => $local->code,
                    'brand_code' => $vehicle->brand_code,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'No existe configuración organizacional para este centro y marca',
                ], 422);
            }

            // 5. ✅ CREAR APPOINTMENT (REPLICANDO AgendarCita.php líneas 2344-2464)
            $appointment = new Appointment();
            $appointment->appointment_number = 'CITA-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $appointment->vehicle_id = $vehicle->id;
            $appointment->premise_id = $local->id;
            $appointment->customer_ruc = $user->document_number;

            // Separar nombre y apellido
            $nameParts = explode(' ', $user->name);
            $appointment->customer_name = $nameParts[0] ?? $user->name;
            $appointment->customer_last_name = implode(' ', array_slice($nameParts, 1)) ?: '';

            $appointment->customer_email = $user->email;
            $appointment->customer_phone = $user->phone;
            $appointment->appointment_date = $request->appointment_date;
            $appointment->appointment_time = $request->appointment_time;

            // ✅ CAMPOS CRÍTICOS PARA OFERTAS
            $appointment->vehicle_brand_code = $vehicle->brand_code;
            $appointment->center_code = $local->code;
            $appointment->vehicle_plate = $vehicle->license_plate;

            // ✅ DETECTAR CLIENTE WILDCARD
            $isWildcardClient = $user->c4c_internal_id === '1200166011';

            // ✅ PACKAGE_ID: Solo para clientes NO wildcard
            $appointment->package_id = $isWildcardClient ? null : null; // Se calculará por job

            // Servicio
            $serviceModes = [];
            if ($request->service_mode === 'express') {
                $serviceModes[] = 'express';
            }
            $appointment->service_mode = implode(', ', $serviceModes) ?: 'regular';
            $appointment->maintenance_type = $request->maintenance_type;
            $appointment->comments = $request->comments;

            // ✅ WILDCARD SELECTIONS
            if ($isWildcardClient && (
                !empty($request->additional_services) ||
                !empty($request->campaign_id)
            )) {
                $wildcardSelections = [];

                if (!empty($request->additional_services)) {
                    $services = AdditionalService::whereIn('id', $request->additional_services)->pluck('name')->toArray();
                    $wildcardSelections['servicios_adicionales'] = $services;
                }

                if (!empty($request->campaign_id)) {
                    $campaign = \App\Models\Campana::find($request->campaign_id);
                    if ($campaign) {
                        $wildcardSelections['campanas'] = [$campaign->titulo];
                    }
                }

                $appointment->wildcard_selections = json_encode($wildcardSelections);
            }

            $appointment->status = 'pending';
            $appointment->is_synced = false;
            $appointment->save();

            Log::info('[API] Appointment creado', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
            ]);

            // 6. ✅ GUARDAR SERVICIOS ADICIONALES
            if (!empty($request->additional_services)) {
                foreach ($request->additional_services as $serviceId) {
                    $appointment->additionalServices()->attach($serviceId);
                }
            }

            // 7. ✅ DESPACHAR JOBS (REPLICANDO AgendarCita.php líneas 2499-2502)
            $jobId = (string) Str::uuid();

            // Preparar datos para C4C
            $fechaHoraInicio = Carbon::parse($request->appointment_date . ' ' . $request->appointment_time);
            $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes(45);

            $citaData = [
                'customer_id' => $user->c4c_internal_id ?? '1270002726',
                'employee_id' => '1740',
                'start_date' => $fechaHoraInicio->format('Y-m-d H:i'),
                'end_date' => $fechaHoraFin->format('Y-m-d H:i'),
                'center_id' => $local->code,
                'vehicle_plate' => $vehicle->license_plate,
                'customer_name' => $appointment->customer_name . ' ' . $appointment->customer_last_name,
                'notes' => $request->comments ?: null,
                'express' => strpos($appointment->service_mode, 'express') !== false ? 'true' : 'false',
            ];

            $appointmentData = [
                'appointment_number' => $appointment->appointment_number,
                'servicios_adicionales' => $request->additional_services ?? [],
                'campanas_disponibles' => [],
            ];

            // Despachar jobs
            EnviarCitaC4CJob::dispatch($citaData, $appointmentData, $jobId, $appointment->id);

            ProcessAppointmentAfterCreationJob::dispatch($appointment->id)
                ->delay(now()->addMinutes(1));

            Log::info('[API] Jobs despachados', [
                'job_id' => $jobId,
                'appointment_id' => $appointment->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'appointment_number' => $appointment->appointment_number,
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'customer_name' => $appointment->customer_name . ' ' . $appointment->customer_last_name,
                    'vehicle_plate' => $vehicle->license_plate,
                    'premise_name' => $local->name,
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'maintenance_type' => $appointment->maintenance_type,
                    'created_at' => $appointment->created_at->toIso8601String(),
                ],
                'message' => 'Cita agendada exitosamente. Se procesará la sincronización con C4C.'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('[API] Error creando cita', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
                'message' => 'Ocurrió un error al crear la cita'
            ], 500);
        }
    }

    /**
     * Obtener detalle de una cita
     *
     * GET /api/v1/appointments/{appointmentNumber}
     *
     * @param string $appointmentNumber
     * @return JsonResponse
     */
    public function show(string $appointmentNumber): JsonResponse
    {
        try {
            $appointment = Appointment::with(['vehicle', 'premise', 'additionalServices'])
                ->where('appointment_number', $appointmentNumber)
                ->first();

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'error' => 'Cita no encontrada',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'appointment_number' => $appointment->appointment_number,
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                    'sync_status' => [
                        'is_synced' => $appointment->is_synced,
                        'c4c_uuid' => $appointment->c4c_uuid,
                        'synced_at' => $appointment->synced_at?->toIso8601String(),
                    ],
                    'offer_status' => [
                        'has_offer' => !empty($appointment->c4c_offer_id),
                        'c4c_offer_id' => $appointment->c4c_offer_id,
                        'created_at' => $appointment->offer_created_at?->toIso8601String(),
                        'package_id' => $appointment->package_id,
                    ],
                    'customer' => [
                        'name' => $appointment->customer_name . ' ' . $appointment->customer_last_name,
                        'document_number' => $appointment->customer_ruc,
                        'phone' => $appointment->customer_phone,
                        'email' => $appointment->customer_email,
                    ],
                    'vehicle' => [
                        'license_plate' => $appointment->vehicle->license_plate ?? $appointment->vehicle_plate,
                        'model' => $appointment->vehicle->model ?? 'N/A',
                        'year' => $appointment->vehicle->year ?? 'N/A',
                    ],
                    'premise' => [
                        'code' => $appointment->premise->code ?? $appointment->center_code,
                        'name' => $appointment->premise->name ?? 'N/A',
                    ],
                    'appointment_date' => $appointment->appointment_date,
                    'appointment_time' => $appointment->appointment_time,
                    'maintenance_type' => $appointment->maintenance_type,
                    'additional_services' => $appointment->additionalServices->map(function ($service) {
                        return [
                            'id' => $service->id,
                            'name' => $service->name,
                        ];
                    }),
                    'created_at' => $appointment->created_at->toIso8601String(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error obteniendo cita', [
                'appointment_number' => $appointmentNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Obtener historial de citas de un cliente
     *
     * GET /api/v1/customers/{document}/appointments
     *
     * @param string $document
     * @param Request $request
     * @return JsonResponse
     */
    public function customerAppointments(string $document, Request $request): JsonResponse
    {
        try {
            $query = Appointment::where('customer_ruc', $document)
                ->with(['vehicle', 'premise'])
                ->orderBy('appointment_date', 'desc')
                ->orderBy('appointment_time', 'desc');

            // Filtros opcionales
            if ($request->has('status')) {
                $query->whereIn('status', explode(',', $request->status));
            }

            if ($request->has('from_date')) {
                $query->where('appointment_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->where('appointment_date', '<=', $request->to_date);
            }

            $limit = $request->get('limit', 10);
            $appointments = $query->limit($limit)->get();

            return response()->json([
                'success' => true,
                'data' => $appointments->map(function ($appointment) {
                    return [
                        'appointment_number' => $appointment->appointment_number,
                        'status' => $appointment->status,
                        'vehicle_plate' => $appointment->vehicle->license_plate ?? $appointment->vehicle_plate,
                        'premise_name' => $appointment->premise->name ?? 'N/A',
                        'appointment_date' => $appointment->appointment_date,
                        'appointment_time' => $appointment->appointment_time,
                        'maintenance_type' => $appointment->maintenance_type,
                    ];
                }),
                'meta' => [
                    'total' => $appointments->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error obteniendo citas del cliente', [
                'document' => $document,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }
}
