<?php

namespace App\Services\Notifications;

use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppointmentWhatsappService
{
    /* Enviar notificación WhatsApp para cita CREADA */
    public function sendAppointmentCreated(Appointment $appointment, array $cliente, array $vehiculo): void
    {
        $contentSid = config('services.twilio.register_appointment');
        $variables = $this->buildContentVariables($appointment, $cliente, $vehiculo);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'CREADA');
    }

    /* Enviar notificación WhatsApp para cita REPROGRAMADA */
    public function sendAppointmentRescheduled(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): void
    {
        $contentSid = config('services.twilio.register_rescheduled');
        $variables = $this->buildRescheduledVariables($appointment, $cliente, $vehiculo, $cambiosRealizados);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'REPROGRAMADA');
    }

    /* Enviar notificación WhatsApp para cita CANCELADA */
    public function sendAppointmentCancelled(Appointment $appointment, array $cliente, array $vehiculo, string $motivoCancelacion): void
    {
        $contentSid = config('services.twilio.register_annulled');
        $variables = $this->buildCancelledVariables($appointment, $cliente, $vehiculo, $motivoCancelacion);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'CANCELADA');
    }

    /* Enviar notificación WhatsApp de RECORDATORIO de cita */
    public function sendAppointmentReminder(Appointment $appointment, array $cliente, array $vehiculo): void
    {
        $contentSid = config('services.twilio.register_reminder');
        $variables = $this->buildReminderVariables($appointment, $cliente, $vehiculo);

        $this->sendWhatsAppNotification($appointment, $contentSid, $variables, 'RECORDATORIO');
    }

    /* Lógica común de envío a Twilio */
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

    /* Construir variables para template de cita creada */
    protected function buildContentVariables(Appointment $appointment, array $cliente, array $vehiculo): array
    {
        // Formatear fecha y hora
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
            '5' => $appointment->premise->name ?? '',
            '6' => $appointment->maintenance_type ?? '',
            '7' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Construir variables para template de cita reprogramada */
    protected function buildRescheduledVariables(Appointment $appointment, array $cliente, array $vehiculo, array $cambiosRealizados): array
    {
        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => $cambiosRealizados['Fecha']['nuevo'] . ' a las ' . $cambiosRealizados['Hora']['nuevo'],
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
            '5' => $cambiosRealizados['Sede']['nuevo'] ?? $appointment->premise->name ?? '',
            '6' => $appointment->maintenance_type ?? '',
            '7' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Construir variables para template de cita cancelada */
    protected function buildCancelledVariables(Appointment $appointment, array $cliente, array $vehiculo, string $motivoCancelacion): array
    {
        // Formatear fecha y hora con formato estándar: dd/mm/yyyy a las HH:mm
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
            '5' => $appointment->premise->name ?? '',
            '6' => $appointment->maintenance_type ?? '',
            '7' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }

    /* Construir variables para template de recordatorio */
    protected function buildReminderVariables(Appointment $appointment, array $cliente, array $vehiculo): array
    {
        // Formatear fecha y hora
        $fechaFormateada = \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y');
        $horaFormateada = \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i');
        $fechaHora = "{$fechaFormateada} a las {$horaFormateada}";

        return [
            '1' => trim(($cliente['nombres'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')),
            '2' => $fechaHora,
            '3' => $vehiculo['modelo'] ?? $appointment->vehicle->model ?? '',
            '4' => $vehiculo['placa'] ?? $appointment->vehicle_plate ?? '',
            '5' => $appointment->premise->name ?? '',
            '6' => $appointment->maintenance_type ?? '',
            '7' => $appointment->comments ?: 'Sin Comentarios',
        ];
    }
}
