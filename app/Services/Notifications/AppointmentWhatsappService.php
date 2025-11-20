<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class AppointmentWhatsappService
{
    public function sendAppointmentCreated(Appointment $appointment, array $cliente, array $vehiculo): void
    {
      
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');
        $from = config('services.twilio.whatsapp_from');
        $contentSid = config('services.twilio.register_appointment');

        if (! $accountSid || ! $authToken || ! $from || ! $contentSid) {
            Log::warning('📲 [WhatsApp] Configuración Twilio incompleta, se omite envío', [
                'appointment_id' => $appointment->id,
            ]);
            return;
        }

        $to = 'whatsapp:+51' . $appointment->customer_phone;

        $variables = $this->buildContentVariables($appointment, $cliente, $vehiculo);

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
}
