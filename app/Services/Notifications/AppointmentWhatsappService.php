<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class AppointmentWhatsappService
{
    public function sendAppointmentCreated(Appointment $appointment, array $cliente, array $vehiculo, ?string $contentSid = null, ?array $cambiosRealizados = null): void
    {

        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');
        $contentSid = $contentSid ?? config('services.twilio.register_appointment');

        if (! $accountSid || ! $authToken || ! $from || ! $contentSid) {
            Log::warning('📲 [WhatsApp] Configuración Twilio incompleta, se omite envío', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        $isRescheduled = $contentSid === config('services.twilio.register_rescheduled');

        Log::info('📲 [WhatsApp] Preparando envío de notificación', [
            'appointment_id' => $appointment->id,
            'contentSid' => $contentSid,
            'template_type' => $isRescheduled ? 'REPROGRAMADA' : 'CREADA',
            'tiene_cambios' => !empty($cambiosRealizados),
        ]);

        $to = 'whatsapp:+51' . $appointment->customer_phone;

        $variables = $isRescheduled && !empty($cambiosRealizados)
            ? $this->buildRescheduledVariables($appointment, $cliente, $vehiculo, $cambiosRealizados)
            : $this->buildContentVariables($appointment, $cliente, $vehiculo);

        Log::info('📲 [WhatsApp] Variables construidas para envío', [
            'appointment_id' => $appointment->id,
            'template_type' => $isRescheduled ? 'REPROGRAMADA' : 'CREADA',
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

    protected function buildContentVariables(Appointment $appointment, array $cliente, array $vehiculo): array
    {
        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => trim(($appointment->appointment_date ?? '') . ' ' . ($appointment->appointment_time ?? '')),
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
            '5' => $appointment->premise->name ?? '',
            '6' => $appointment->maintenance_type ?? '',
            '7' => $appointment->comments ?? '',
        ];
    }

    /**
     * Construir variables para template de cita reprogramada
     * Las variables dependen de lo que el template de Twilio espera
     */
    protected function buildRescheduledVariables(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): array
    {

        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => $cambiosRealizados['Fecha']['nuevo'] ?? $appointment->appointment_date ?? '',
            '3' => $cambiosRealizados['Hora']['nuevo'] ?? $appointment->appointment_time ?? '',
            '4' => $cambiosRealizados['Sede']['nuevo'] ?? $appointment->premise->name ?? '',
            '5' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '6' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
        ];
    }
}
