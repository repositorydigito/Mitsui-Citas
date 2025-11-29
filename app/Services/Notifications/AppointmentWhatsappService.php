<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


/**
 * RICARDO - Servicio para enviar notificaciones WhatsApp de citas (Cita Creada, Reprogramada, Cancelada y Recordatorio).
 */

class AppointmentWhatsappService
{
    /* Enviar notificación WhatsApp de cita CREADA */
    public function sendAppointmentCreated(Appointment $appointment, array $cliente, array $vehiculo): void
    {
        $contentSid = config('services.twilio.register_appointment');
        $variables = $this->buildContentVariables($appointment, $cliente, $vehiculo);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'CREADA');
    }

    /* Enviar notificación WhatsApp de cita REPROGRAMADA */
    public function sendAppointmentRescheduled(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): void
    {
        $contentSid = config('services.twilio.register_rescheduled');
        $variables = $this->buildRescheduledVariables($appointment, $cliente, $vehiculo, $cambiosRealizados);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'REPROGRAMADA');
    }

    /* Enviar notificación WhatsApp de cita CANCELADA */
    public function sendAppointmentCancelled(Appointment $appointment, array $cliente, array $vehiculo, string $motivoCancelacion): void
    {
        $contentSid = config('services.twilio.register_annulled');
        $variables = $this->buildCancelledVariables($appointment, $cliente, $vehiculo, $motivoCancelacion);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'CANCELADA');
    }

    /** Enviar notificación WhatsApp de RECORDATORIO de cita */
    public function sendAppointmentReminder(Appointment $appointment, array $cliente, array $vehiculo): void
    {
        $contentSid = config('services.twilio.register_reminder');
        $variables = $this->buildReminderVariables($appointment, $cliente, $vehiculo);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'RECORDATORIO');
    }

    /* Lógica de envío a Twilio */
    protected function sendWhatsAppNotification(Appointment $appointment, string $contentSid, array $variables, string $templateType): void
    {
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');

        if (! $accountSid || ! $authToken || ! $from || ! $contentSid) {
            Log::warning('📲 [WhatsApp] Configuración Twilio incompleta, se omite envío', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        Log::info('📲 [WhatsApp] Preparando envío de notificación', [
            'appointment_id' => $appointment->id,
            'contentSid' => $contentSid,
            'template_type' => $templateType,
        ]);

        $to = 'whatsapp:+51' . $appointment->customer_phone;

        Log::info('📲 [WhatsApp] Variables construidas para envío', [
            'appointment_id' => $appointment->id,
            'template_type' => $templateType,
            'variables' => $variables,
        ]);

        $payload = [
            'To' => $to,
            'From' => $from,
            'ContentSid' => $contentSid,
            'ContentVariables' => json_encode($variables),
        ];

        $response = Http::asForm()
            ->withBasicAuth($accountSid, $authToken)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", $payload);

        Log::info('📲 [WhatsApp] Respuesta Twilio', [
            'appointment_id' => $appointment->id,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
    }


    /* Template de cita creada */
    protected function buildContentVariables(Appointment $appointment, array $cliente, array $vehiculo): array
    {
        // Formatear fecha y hora
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?: 'Sin Nombres') . ' ' . ($cliente['apellidos'] ?: 'Sin Apellidos')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?: 'Sin Modelo',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?: 'Sin Placa',
            '5' => $appointment->premise->name ?: 'Sin Sede',
            '6' => $this->buildServiceTypes($appointment),
            '7' => $this->buildMaintenanceDetails($appointment),
            '8' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Template de cita reprogramada */
    protected function buildRescheduledVariables(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): array
    {
        return [
            '1' => trim(($cliente['nombres'] ?: 'Sin Nombres') . ' ' . ($cliente['apellidos'] ?: 'Sin Apellidos')),
            '2' => $cambiosRealizados['Fecha']['nuevo'] . ' a las ' . $cambiosRealizados['Hora']['nuevo'],
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?: 'Sin Modelo',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?: 'Sin Placa',
            '5' => $cambiosRealizados['Sede']['nuevo'] ?? $appointment->premise->name ?: 'Sin Sede',
            '6' => $this->buildServiceTypes($appointment),
            '7' => $this->buildMaintenanceDetails($appointment),
            '8' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Template de cita cancelada */
    protected function buildCancelledVariables(Appointment $appointment, array $cliente, array $vehiculo, string $motivoCancelacion): array
    {
        // Formatear fecha y hora
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?: 'Sin Nombres') . ' ' . ($cliente['apellidos'] ?: 'Sin Apellidos')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?: 'Sin Modelo',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?: 'Sin Placa',
            '5' => $appointment->premise->name ?: 'Sin Sede',
            '6' => $this->buildServiceTypes($appointment),
            '7' => $this->buildMaintenanceDetails($appointment),
            '8' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Template de recordatorio */
    protected function buildReminderVariables(Appointment $appointment, array $cliente, array $vehiculo): array
    {
        // Formatear fecha y hora
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?: 'Sin Nombres') . ' ' . ($cliente['apellidos'] ?: 'Sin Apellidos')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?: 'Sin Modelo',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?: 'Sin Placa',
            '5' => $appointment->premise->name ?: 'Sin Sede',
            '6' => $this->buildServiceTypes($appointment),
            '7' => $this->buildMaintenanceDetails($appointment),
            '8' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /**
     * Construir TIPOS de servicio (Variable 6)
     *
     * RICARDO - Devuelve los tipos genéricos de servicio:
     * - "Mantenimiento periódico" si hay maintenance_type
     * - "Otros Servicios" si hay additionalServices
     * - "Mantenimiento periódico, Otros Servicios" si hay ambos
     */
    protected function buildServiceTypes(Appointment $appointment): string
    {
        $serviceTypes = [];

        if ($appointment->maintenance_type) {
            $serviceTypes[] = 'Mantenimiento periódico';
        }

        if ($appointment->additionalServices && $appointment->additionalServices->count() > 0) {
            $serviceTypes[] = 'Otros Servicios';
        }

        return !empty($serviceTypes) ? implode(', ', $serviceTypes) : 'Servicio no encontrado';
    }

    /**
     * Construir DETALLES de mantenimiento (Variable 7)
     *
     * RICARDO - Devuelve la lista detallada de mantenimientos:
     * - maintenance_type (ej: "10,000 km")
     * - additionalServices (ej: "Alineado, Balanceo")
     */
    protected function buildMaintenanceDetails(Appointment $appointment): string
    {
        $maintenanceList = [];

        // Agregar maintenance_type
        if ($appointment->maintenance_type) {
            $maintenanceList[] = $appointment->maintenance_type;
        }

        // Agregar servicios adicionales
        if ($appointment->additionalServices && $appointment->additionalServices->count() > 0) {
            $additionalNames = $appointment->additionalServices
                ->map(function ($appointmentService) {
                    return $appointmentService->additionalService->name ?? null;
                })
                ->filter()
                ->toArray();

            if (!empty($additionalNames)) {
                $maintenanceList = array_merge($maintenanceList, $additionalNames);
            }
        }

        return !empty($maintenanceList) ? implode(', ', $maintenanceList) : 'Mantenimiento no encontrado';
    }

}
