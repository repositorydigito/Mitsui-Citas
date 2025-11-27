<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\AppointmentRescheduled;
use App\Mail\RecordatorioCita;
use App\Services\Notifications\AppointmentWhatsappService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * RICARDO - Comando para enviar recordatorios automáticos de citas.
 * Busca citas para MAÑANA (24h antes) y envía email + WhatsApp a los clientes.
 */
class SendAppointmentRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointments:send-reminders {--force : Forzar reenvío ignorando recordatorios existentes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar recordatorios de citas 24 horas antes (para mañana)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        $this->info('🔔 Iniciando envío de recordatorios de citas...');

        if ($force) {
            $this->warn('⚡ Modo FORCE activado - Se ignorarán recordatorios existentes');
        }

        // Obtener fecha para MAÑANA (24h antes)
        $targetDate = Carbon::now()->addDay();

        Log::info('📅 [Recordatorios] Buscando citas para mañana', [
            'target_date' => $targetDate->format('Y-m-d'),
            'force_mode' => $force,
        ]);

        // Buscar citas para mañana (appointment_date) con status confirmado o pendiente y $targetDate para comparar con la fecha de 1 dia antes
        $appointments = Appointment::whereDate('appointment_date', $targetDate)
            ->whereIn('status', ['confirmed', 'pending'])
            ->with(['premise', 'vehicle', 'additionalServices.additionalService'])
            ->get();

        $this->info("📊 Encontradas {$appointments->count()} citas para mañana");

        if ($appointments->isEmpty()) {
            $this->info('✅ No hay citas programadas para mañana');
            return Command::SUCCESS;
        }

        $recordatoriosCreados = 0;
        $recordatoriosExistentes = 0;
        $errores = 0;

        foreach ($appointments as $appointment) {
            try {

                // Verificar si ya existe un recordatorio para esta cita (solo si no es modo force)
                if (!$force) {
                    $existeRecordatorio = AppointmentRescheduled::where('appointment_id', $appointment->id)
                        ->whereDate('reminder_date', $targetDate)
                        ->exists();

                    if ($existeRecordatorio) {
                        $recordatoriosExistentes++;
                        $this->warn("⚠️  Recordatorio ya existe para cita #{$appointment->id}");
                        continue;
                    }
                }

                // Preparar datos del cliente
                $datosCliente = [
                    'nombres' => $appointment->customer_name,
                    'apellidos' => $appointment->customer_last_name ?? '',
                    'email' => $appointment->customer_email,
                    'celular' => $appointment->customer_phone,
                ];

                // Preparar datos del vehículo
                $datosVehiculo = [
                    'marca' => $appointment->vehicle->brand_name ?? '',
                    'modelo' => $appointment->vehicle->model ?? '',
                    'placa' => $appointment->vehicle->license_plate ?? $appointment->vehicle_plate ?? '',
                ];

                // 1. Enviar EMAIL
                Mail::to($appointment->customer_email)
                    ->send(new RecordatorioCita($appointment, $datosCliente, $datosVehiculo));

                $this->info("  📧 Email enviado a {$appointment->customer_email}");

                // 2. Enviar WhatsApp
                app(AppointmentWhatsappService::class)->sendAppointmentReminder(
                    $appointment,
                    $datosCliente,
                    $datosVehiculo
                );

                $this->info("  📲 WhatsApp enviado a {$appointment->customer_phone}");

                // 3. Crear registro de recordatorio enviado
                AppointmentRescheduled::create([
                    'appointment_id' => $appointment->id,
                    'reminder_date' => Carbon::now(),
                    'status_mail' => 'sent',
                    'status_notifications' => 'sent',
                    'sent_at' => Carbon::now(),
                ]);

                $recordatoriosCreados++;
                $this->info("✅ Recordatorio completado para cita #{$appointment->id} - {$appointment->customer_name}");

                Log::info('✅ [Recordatorios] Recordatorio enviado', [
                    'appointment_id' => $appointment->id,
                    'customer_name' => $appointment->customer_name,
                    'appointment_date' => $appointment->appointment_date,
                    'email_sent' => true,
                    'whatsapp_sent' => true,
                ]);

            } catch (\Exception $e) {
                $errores++;
                $this->error("❌ Error procesando cita #{$appointment->id}: {$e->getMessage()}");

                // Intentar crear registro con status failed
                try {
                    AppointmentRescheduled::create([
                        'appointment_id' => $appointment->id,
                        'reminder_date' => Carbon::now(),
                        'status_mail' => 'failed',
                        'status_notifications' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                } catch (\Exception $dbError) {

                }

                Log::error('❌ [Recordatorios] Error enviando recordatorio', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Resumen en los Logs
        $this->newLine();
        $this->info('📊 Resumen de procesamiento:');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Citas encontradas', $appointments->count()],
                ['Recordatorios creados', $recordatoriosCreados],
                ['Ya existentes', $recordatoriosExistentes],
                ['Errores', $errores],
            ]
        );

        Log::info('🏁 [Recordatorios] Comando finalizado', [
            'total_citas' => $appointments->count(),
            'creados' => $recordatoriosCreados,
            'existentes' => $recordatoriosExistentes,
            'errores' => $errores,
        ]);

        return Command::SUCCESS;
    }
}
