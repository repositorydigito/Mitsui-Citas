<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class AppointmentWhatsappService
{
    /**
     * Enviar notificación WhatsApp para cita CREADA
     */
    public function sendAppointmentCreated(Appointment $appointment, array $cliente, array $vehiculo): void
    {
        $contentSid = config('services.twilio.register_appointment');
        $variables = $this->buildContentVariables($appointment, $cliente, $vehiculo);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'CREADA');
    }

    /**
     * Enviar notificación WhatsApp para cita REPROGRAMADA
     */
    public function sendAppointmentRescheduled(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): void
    {
        $contentSid = config('services.twilio.register_rescheduled');
        $variables = $this->buildRescheduledVariables($appointment, $cliente, $vehiculo, $cambiosRealizados);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'REPROGRAMADA');
    }

    /**
     * Lógica común de envío a Twilio (DRY principle)
     */
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

    /**
     * Construir variables para template de cita creada
     */
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
            '7' => $appointment->comments ?? '',
        ];
    }
}
