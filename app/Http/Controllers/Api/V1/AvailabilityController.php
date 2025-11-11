<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Bloqueo;
use App\Models\Local;
use App\Services\C4C\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller para consultar disponibilidad de horarios
 */
class AvailabilityController extends Controller
{
    protected AvailabilityService $availabilityService;

    public function __construct(AvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

    /**
     * Consultar horarios disponibles para fecha y local
     *
     * POST /api/v1/availability/check
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'premise_code' => 'required|string|exists:premises,code',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de validación inválidos',
                'details' => $validator->errors()
            ], 400);
        }

        $premiseCode = $request->premise_code;
        $date = $request->date;

        Log::info('[API] Consultando disponibilidad', [
            'premise_code' => $premiseCode,
            'date' => $date,
        ]);

        try {
            // Obtener el local
            $local = Local::where('code', $premiseCode)->first();

            if (!$local) {
                return response()->json([
                    'success' => false,
                    'error' => 'Local no encontrado',
                ], 404);
            }

            // Mapear código local a C4C
            $c4cCenterId = $this->mapPremiseToC4C($premiseCode);

            // Obtener slots de C4C
            $result = $this->availabilityService->getAvailableSlotsWithCache($c4cCenterId, $date, 300);

            if (!$result['success']) {
                Log::error('[API] Error consultando C4C', [
                    'error' => $result['error'] ?? 'Error desconocido',
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Error consultando disponibilidad',
                    'message' => $result['error'] ?? 'No se pudo obtener disponibilidad de C4C'
                ], 503);
            }

            // Filtrar slots ocupados localmente
            $availableSlots = $this->filterOccupiedSlots($result['slots'], $date, $premiseCode);

            Log::info('[API] Disponibilidad obtenida', [
                'total_slots_c4c' => count($result['slots']),
                'available_slots' => count($availableSlots),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'premise' => [
                        'code' => $premiseCode,
                        'name' => $local->name,
                    ],
                    'slots' => $availableSlots,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('[API] Error consultando disponibilidad', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor',
            ], 500);
        }
    }

    /**
     * Mapear código de local a código C4C
     */
    private function mapPremiseToC4C(string $premiseCode): string
    {
        $mapping = [
            'M013' => 'M013',
            'M023' => 'M023',
            'M303' => 'M303',
            'M313' => 'M313',
            'M033' => 'M033',
            'L013' => 'L013',
        ];

        return $mapping[$premiseCode] ?? $premiseCode;
    }

    /**
     * Filtrar slots ocupados en BD local
     * Basado en el método filtrarSlotsOcupados() de AgendarCita.php
     */
    private function filterOccupiedSlots(array $slotsC4C, string $date, string $premiseCode): array
    {
        // Obtener el local para tener el ID
        $local = Local::where('code', $premiseCode)->first();
        if (!$local) {
            return $slotsC4C;
        }

        // Obtener citas existentes para este local y fecha
        $existingAppointments = Appointment::where('premise_id', $local->id)
            ->where('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->pluck('appointment_time')
            ->map(function ($time) {
                return \Carbon\Carbon::parse($time)->format('H:i');
            })
            ->toArray();

        // Obtener bloqueos para este local y fecha
        // Usar el campo 'premises' que es un string, no una relación
        $blocks = Bloqueo::where('premises', $premiseCode)
            ->where(function ($query) use ($date) {
                $query->where('start_date', '<=', $date)
                    ->where('end_date', '>=', $date);
            })
            ->get()
            ->map(function ($bloqueo) {
                // Si es todo el día, devolver null para marcarlo especialmente
                if ($bloqueo->all_day) {
                    return null; // Marcar como bloqueo de todo el día
                }
                return substr($bloqueo->start_time, 0, 5); // HH:MM
            })
            ->filter()
            ->toArray();

        $occupiedSlots = array_merge($existingAppointments, $blocks);

        // Filtrar y formatear slots
        $availableSlots = [];
        foreach ($slotsC4C as $slot) {
            // El servicio C4C retorna slots con estructura específica
            $time = is_array($slot) ? ($slot['start_time_formatted'] ?? $slot['time'] ?? '') : $slot;
            $time = substr($time, 0, 5); // Asegurar formato HH:MM

            if (!in_array($time, $occupiedSlots)) {
                $availableSlots[] = [
                    'time' => $time,
                    'available' => true,
                ];
            }
        }

        return $availableSlots;
    }
}
