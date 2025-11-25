<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\C4C\AppointmentService;
use App\Jobs\DownloadProductsJob;
use App\Mail\CitaCreada;
use App\Services\Notifications\AppointmentWhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnviarCitaC4CJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $citaData;

    public array $appointmentData;

    public string $jobId;

    public int $appointmentId;

    public bool $isRescheduled;

    /**
     * Tiempo de vida del job en segundos
     */
    public int $timeout = 300; // 5 minutos

    /**
     * Número de intentos máximos
     */
    public int $tries = 3;

    public function __construct(array $citaData, array $appointmentData, string $jobId, int $appointmentId, bool $isRescheduled)
    {
        $this->citaData = $citaData;
        $this->appointmentData = $appointmentData;
        $this->jobId = $jobId;
        $this->appointmentId = $appointmentId;
        $this->isRescheduled = $isRescheduled;
    }

    public function handle(): void
    {
        try {
            Log::info('[EnviarCitaC4CJob] Iniciando envío de cita a C4C', [
                'job_id' => $this->jobId,
                'appointment_id' => $this->appointmentId,
            ]);

            // Actualizar status a "processing"
            Cache::put("cita_job_{$this->jobId}", [
                'status' => 'processing',
                'progress' => 25,
                'message' => 'Enviando cita a C4C...',
                'updated_at' => now(),
            ], 600); // 10 minutos

            // Enviar a C4C
            $appointmentService = app(AppointmentService::class);
            $resultadoC4C = $appointmentService->create($this->citaData);

            if (! $resultadoC4C['success']) {
                throw new \Exception('Error al enviar cita a C4C: ' . ($resultadoC4C['error'] ?? 'Error desconocido'));
            }

            Log::info('[EnviarCitaC4CJob] ✅ Cita enviada exitosamente a C4C', $resultadoC4C['data'] ?? []);

            // Actualizar progress
            Cache::put("cita_job_{$this->jobId}", [
                'status' => 'processing',
                'progress' => 75,
                'message' => 'Actualizando base de datos...',
                'updated_at' => now(),
            ], 600);

            // Actualizar el registro en la base de datos
            $appointment = Appointment::find($this->appointmentId);
            if ($appointment) {
                // ✅ SOLUCIÓN CORRECTA: BUSCAR UUID DONDE DEBE ESTAR
                $c4cUuid = null;
                $c4cAppointmentId = null;

                // 🎯 MÉTODO 1: Buscar directamente en la respuesta (AppointmentService corregido)
                if (isset($resultadoC4C['uuid'])) {
                    $c4cUuid = $resultadoC4C['uuid'];
                }
                if (isset($resultadoC4C['appointment_id'])) {
                    $c4cAppointmentId = $resultadoC4C['appointment_id'];
                }

                // 🔄 FALLBACK: Buscar en data si no está en el nivel superior
                if (!$c4cUuid && isset($resultadoC4C['data']['uuid'])) {
                    $c4cUuid = $resultadoC4C['data']['uuid'];
                }
                if (!$c4cAppointmentId && isset($resultadoC4C['data']['id'])) {
                    $c4cAppointmentId = $resultadoC4C['data']['id'];
                }

                // 🆘 ÚLTIMO RECURSO: Regex en toda la respuesta
                if (!$c4cUuid) {
                    $responseString = print_r($resultadoC4C, true);
                    if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $responseString, $matches)) {
                        $c4cUuid = $matches[1];
                    }
                }

                // ✅ OBTENER PACKAGE_ID usando ProductService con lógica dinámica (solo para referencia)
                $packageId = null;

                // 🔍 DEBUG: Estado del appointment al llegar
                Log::info('🔍 [EnviarCitaC4CJob] Estado appointment al llegar', [
                    'appointment_id' => $appointment->id,
                    'current_package_id' => $appointment->package_id,
                    'maintenance_type' => $appointment->maintenance_type,
                    'condition_maintenance_type' => $appointment->maintenance_type ? 'TRUE' : 'FALSE',
                    'condition_no_package_id' => !$appointment->package_id ? 'TRUE' : 'FALSE',
                    'condition_combined' => ($appointment->maintenance_type && !$appointment->package_id) ? 'TRUE' : 'FALSE'
                ]);

                if ($appointment->maintenance_type && !$appointment->package_id) {
                    try {
                        $productService = app(\App\Services\C4C\ProductService::class);

                        // Cargar vehículo si no está cargado
                        if (!$appointment->relationLoaded('vehicle')) {
                            $appointment->load('vehicle');
                        }

                        $packageId = $productService->obtenerPaquetePorTipo(
                            $appointment->maintenance_type,
                            $appointment->vehicle
                        );

                        Log::info('📦 Package ID obtenido dinámicamente para appointment', [
                            'appointment_id' => $appointment->id,
                            'maintenance_type' => $appointment->maintenance_type,
                            'package_id' => $packageId,
                            'vehicle_tipo_valor_trabajo' => $appointment->vehicle?->tipo_valor_trabajo,
                            'vehicle_brand_code' => $appointment->vehicle?->brand_code
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('⚠️ Error obteniendo package_id dinámicamente', [
                            'appointment_id' => $appointment->id,
                            'maintenance_type' => $appointment->maintenance_type,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $updateData = [
                    'c4c_uuid' => $c4cUuid,
                    'is_synced' => true,
                    'synced_at' => now(),
                    'status' => 'confirmed',
                ];

                if ($c4cAppointmentId) {
                    $updateData['c4c_appointment_id'] = $c4cAppointmentId;
                }

                // ✅ DETECTAR CLIENTE COMODÍN ANTES DE ASIGNAR PACKAGE_ID
                $user = \App\Models\User::where('document_number', $appointment->customer_ruc)->first();
                $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

                // 🔍 DEBUG: Log detallado de la detección
                Log::info('🔍 [EnviarCitaC4CJob] Detección wildcard', [
                    'appointment_id' => $appointment->id,
                    'customer_ruc' => $appointment->customer_ruc,
                    'user_found' => $user ? 'YES' : 'NO',
                    'user_c4c_id' => $user ? $user->c4c_internal_id : 'NO_USER',
                    'is_wildcard' => $isWildcardClient,
                    'package_id' => $packageId,
                    'condition_packageId' => $packageId ? 'TRUE' : 'FALSE',
                    'condition_not_wildcard' => !$isWildcardClient ? 'TRUE' : 'FALSE'
                ]);

                if ($isWildcardClient) {
                    // 🎯 FORZAR package_id = NULL para clientes wildcard
                    $updateData['package_id'] = null;
                    Log::info('⚠️ [EnviarCitaC4CJob] FORZADO package_id = NULL (cliente comodín)', [
                        'appointment_id' => $appointment->id,
                        'is_wildcard_client' => true,
                        'calculated_package_id' => $packageId,
                        'forced_package_id' => null
                    ]);
                }

                $appointment->update($updateData);

                // 🚀 NUEVO: Disparar descarga de productos si hay package_id (SALTAR PARA CLIENTES WILDCARD)
                $appointment->refresh(); // Refrescar para obtener datos actualizados
                $finalPackageId = $packageId ?: $appointment->package_id;

                if ($finalPackageId && !$isWildcardClient) {
                    $this->dispatchProductDownload($appointment->id, $finalPackageId);
                } else if ($isWildcardClient) {
                    Log::info('⚠️ [EnviarCitaC4CJob] No se disparó descarga de productos (cliente comodín)', [
                        'appointment_id' => $appointment->id,
                        'is_wildcard_client' => true
                    ]);

                    // 🎯 SOLUCIÓN DEFINITIVA: Disparar CreateOfferJob para clientes wildcard
                    Log::info('🎯 [EnviarCitaC4CJob] Disparando CreateOfferJob para cliente wildcard', [
                        'appointment_id' => $appointment->id,
                        'trigger' => 'wildcard_client_detected'
                    ]);

                    \App\Jobs\CreateOfferJob::dispatch($appointment)->onQueue('offers');

                    Log::info('✅ [EnviarCitaC4CJob] CreateOfferJob wildcard encolado', [
                        'appointment_id' => $appointment->id,
                        'queue' => 'offers'
                    ]);
                }

                Log::info('[EnviarCitaC4CJob] Appointment actualizado en BD', [
                    'appointment_id' => $appointment->id,
                    'c4c_uuid' => $c4cUuid,
                    'c4c_appointment_id' => $c4cAppointmentId,
                    'package_id' => $packageId,
                    'uuid_encontrado' => !empty($c4cUuid),
                    'package_id_encontrado' => !empty($packageId),
                ]);
            }

            // Marcar como completado
            Cache::put("cita_job_{$this->jobId}", [
                'status' => 'completed',
                'progress' => 100,
                'message' => '¡Cita confirmada exitosamente!',
                'appointment_number' => $appointment->appointment_number ?? null,
                'updated_at' => now(),
            ], 600);

            // **ENVIAR EMAIL DE CONFIRMACIÓN DESPUÉS DEL ÉXITO EN C4C** 📧
            $this->enviarEmailConfirmacion($appointment);

            Log::info('[EnviarCitaC4CJob] ✅ Job completado exitosamente', [
                'job_id' => $this->jobId,
                'appointment_id' => $this->appointmentId,
            ]);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            Log::error('[EnviarCitaC4CJob] ❌ Error en job', [
                'job_id' => $this->jobId,
                'appointment_id' => $this->appointmentId,
                'error' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            // 🚨 VERIFICAR SI ES UN ERROR FATAL (NO REINTENTABLE)
            if ($this->isFatalError($errorMessage)) {
                Log::warning('[EnviarCitaC4CJob] ⚠️ Error fatal detectado - NO se reintentará', [
                    'job_id' => $this->jobId,
                    'error' => $errorMessage,
                ]);

                // Marcar como fallido permanentemente
                Cache::put("cita_job_{$this->jobId}", [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Error de negocio: ' . $errorMessage,
                    'appointment_id' => $this->appointmentId,
                    'error' => $errorMessage,
                    'fatal' => true,
                    'updated_at' => now(),
                ], 600);

                // Actualizar appointment como fallido
                if ($appointment = Appointment::find($this->appointmentId)) {
                    $appointment->update([
                        'status' => 'failed',
                        'c4c_error' => $errorMessage,
                        'is_synced' => false,
                    ]);
                }

                // NO hacer throw para evitar reintentos - usar fail() en su lugar
                $this->fail($e);

                return;
            }

            // 🔄 ES UN ERROR TEMPORAL - SÍ se puede reintentar
            Log::info('[EnviarCitaC4CJob] 🔄 Error temporal - se reintentará', [
                'job_id' => $this->jobId,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            // Actualizar estado como reintentando
            Cache::put("cita_job_{$this->jobId}", [
                'status' => 'retrying',
                'progress' => 0,
                'message' => "Reintentando... (intento {$this->attempts()}/{$this->tries}): " . $errorMessage,
                'appointment_id' => $this->appointmentId,
                'error' => $errorMessage,
                'attempt' => $this->attempts(),
                'updated_at' => now(),
            ], 600);

            // Re-lanzar la excepción para que Laravel maneje los reintentos
            throw $e;
        }
    }

    /**
     * Manejar cuando el job falla después de todos los intentos
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[EnviarCitaC4CJob] ❌ Job falló después de todos los intentos', [
            'job_id' => $this->jobId,
            'appointment_id' => $this->appointmentId,
            'error' => $exception->getMessage(),
        ]);

        // Actualizar el appointment como fallido
        $appointment = Appointment::find($this->appointmentId);
        if ($appointment) {
            $appointment->update([
                'status' => 'failed',
                'is_synced' => false,
            ]);
        }

        // Actualizar cache con estado final de error
        Cache::put("cita_job_{$this->jobId}", [
            'status' => 'failed',
            'progress' => 0,
            'message' => 'Error al procesar la cita: ' . $exception->getMessage(),
            'error' => $exception->getMessage(),
            'updated_at' => now(),
        ], 600);
    }

    /**
     * Determinar si el error es fatal (no reintentable) o temporal (reintentable)
     */
    private function isFatalError(string $errorMessage): bool
    {
        // Lista de errores fatales que NO deben reintentarse
        $fatalErrorPatterns = [
            // Errores de negocio
            'ya tiene cita(s) abierta(s)', // El vehículo ya tiene cita abierta
            'Customer not found', // Cliente no encontrado
            'Vehicle not found', // Vehículo no encontrado
            'Invalid customer data', // Datos de cliente inválidos
            'Invalid vehicle data', // Datos de vehículo inválidos
            'Duplicate appointment', // Cita duplicada
            'Invalid appointment date', // Fecha de cita inválida
            'Appointment date in the past', // Fecha en el pasado
            'No available slots', // No hay horarios disponibles
            'Invalid center code', // Código de centro inválido
            'Appointment already exists', // Cita ya existe
            'Invalid maintenance type', // Tipo de mantenimiento inválido

            // Errores de validación
            'Validation failed', // Falla de validación
            'Invalid input', // Entrada inválida
            'Missing required field', // Campo requerido faltante
            'Invalid format', // Formato inválido

            // Errores de permisos
            'Unauthorized', // No autorizado
            'Access denied', // Acceso denegado
            'Permission denied', // Permiso denegado
            'Invalid credentials', // Credenciales inválidas

            // Errores de configuración
            'Service not configured', // Servicio no configurado
            'Invalid configuration', // Configuración inválida
        ];

        $errorLower = strtolower($errorMessage);

        Log::info('[EnviarCitaC4CJob] 🔍 Analizando error para determinar si es fatal', [
            'job_id' => $this->jobId,
            'error_message' => $errorMessage,
            'error_lower' => $errorLower,
        ]);

        foreach ($fatalErrorPatterns as $pattern) {
            $patternLower = strtolower($pattern);
            if (str_contains($errorLower, $patternLower)) {
                Log::warning('[EnviarCitaC4CJob] 🚨 ERROR FATAL DETECTADO!', [
                    'job_id' => $this->jobId,
                    'matched_pattern' => $pattern,
                    'error_message' => $errorMessage,
                ]);

                return true;
            }
        }

        Log::info('[EnviarCitaC4CJob] 🔄 Error temporal detectado - se puede reintentar', [
            'job_id' => $this->jobId,
            'error_message' => $errorMessage,
        ]);

        // Si no coincide con ningún patrón fatal, es un error temporal
        return false;
    }

    /**
     * ✅ NUEVO: Obtener valor anidado usando notación de puntos
     */
    private function getNestedValue(array $array, string $path)
    {
        $keys = explode('.', $path);
        $current = $array;

        foreach ($keys as $key) {
            if (!is_array($current) || !isset($current[$key])) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * 🚀 NUEVO: Disparar job de descarga de productos vinculados
     */
    protected function dispatchProductDownload(int $appointmentId, string $packageId): void
    {
        try {
            Log::info('🚀 [EnviarCitaC4CJob] Disparando descarga de productos', [
                'appointment_id' => $appointmentId,
                'package_id' => $packageId,
                'trigger' => 'cita_enviada_a_c4c'
            ]);

            // Disparar job de descarga de productos con appointment_id
            DownloadProductsJob::dispatch($packageId, $appointmentId)
                ->delay(now()->addSeconds(5)); // Pequeño delay para asegurar que la cita esté guardada

            Log::info('✅ [EnviarCitaC4CJob] Job de descarga de productos disparado', [
                'appointment_id' => $appointmentId,
                'package_id' => $packageId
            ]);
        } catch (\Exception $e) {
            Log::error('💥 [EnviarCitaC4CJob] Error disparando job de descarga de productos', [
                'appointment_id' => $appointmentId,
                'package_id' => $packageId,
                'error' => $e->getMessage()
            ]);

            // No re-lanzar la excepción para no fallar el job principal
        }
    }

    /**
     * 📧 NUEVO: Enviar email de confirmación después del éxito en C4C
     */
    protected function enviarEmailConfirmacion(Appointment $appointment): void
    {
        // ✅ PREVENIR DUPLICACIÓN: Si es reprogramada, saltar notificaciones
        if ($this->isRescheduled) {
            Log::info('📧📲 [EnviarCitaC4CJob] SALTANDO notificaciones - cita reprogramada', [
                'appointment_id' => $appointment->id,
                'reason' => 'Notificaciones ya enviadas desde AgendarCita::enviarEmailCitaEditada()',
            ]);
            return;
        }

        try {
            // 🔍 LOG DETALLADO PARA DIAGNÓSTICO
            Log::info('📧 [EnviarCitaC4CJob] ========== INICIANDO ENVÍO EMAIL ==========', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_email' => $appointment->customer_email,
                'maintenance_type' => $appointment->maintenance_type,
                'service_mode' => $appointment->service_mode,
                'package_id' => $appointment->package_id,
                'status' => $appointment->status,
                'is_synced' => $appointment->is_synced,
                'c4c_uuid' => $appointment->c4c_uuid,
                'scenario' => $appointment->maintenance_type ? 'WITH_MAINTENANCE' : 'OTHER_SERVICES_OR_CAMPAIGNS'
            ]);

            // Validar que tenga email del cliente
            if (empty($appointment->customer_email)) {
                Log::error('📧 [EnviarCitaC4CJob] ERROR: Customer email vacío', [
                    'appointment_id' => $appointment->id
                ]);
                return;
            }

            // Preparar datos del cliente
            $datosCliente = [
                'nombres' => $appointment->customer_name,
                'apellidos' => $appointment->customer_last_name,
                'email' => $appointment->customer_email,
                'celular' => $appointment->customer_phone,
            ];

            Log::info('📧 [EnviarCitaC4CJob] Datos del cliente preparados', $datosCliente);

            // Cargar relaciones necesarias antes de enviar el email
            $appointment->load(['additionalServices.additionalService', 'vehicle']);

            Log::info('📧 [EnviarCitaC4CJob] Relaciones cargadas', [
                'additional_services_count' => $appointment->additionalServices->count(),
                'vehicle_loaded' => $appointment->vehicle ? 'YES' : 'NO'
            ]);

            // Preparar datos del vehículo (usar relación vehicle si está disponible)
            $datosVehiculo = [
                'marca' => $appointment->vehicle?->brand_name ?? $appointment->vehicle_brand ?? 'No especificado',
                'modelo' => $appointment->vehicle?->model ?? $appointment->vehicle_model ?? 'No especificado',
                'placa' => $appointment->vehicle?->license_plate ?? $appointment->vehicle_license_plate ?? 'No especificado',
            ];

            Log::info('📧 [EnviarCitaC4CJob] Datos del vehículo preparados', $datosVehiculo);

            // 🔍 LOG ANTES DEL ENVÍO
            Log::info('📧 [EnviarCitaC4CJob] ENVIANDO EMAIL de confirmación...', [
                'to_email' => $appointment->customer_email,
                'appointment_number' => $appointment->appointment_number,
                'cc_email' => 'citasmantenimiento@mitsuiautomotriz.com'
            ]);

            // Enviar el correo de confirmación
            Mail::to($appointment->customer_email)
                ->send(new CitaCreada($appointment, $datosCliente, $datosVehiculo));

            Log::info('📧 [EnviarCitaC4CJob] ✅ EMAIL DE CONFIRMACIÓN ENVIADO EXITOSAMENTE', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email,
                'appointment_number' => $appointment->appointment_number
            ]);

            // después de Mail::to()->send(...)
            app(AppointmentWhatsappService::class)
                ->sendAppointmentCreated($appointment, $datosCliente, $datosVehiculo);

            Log::info('📲 [EnviarCitaC4CJob] Notificación WhatsApp disparada', [
                'appointment_id' => $appointment->id,
                'to_phone' => $appointment->customer_phone,
            ]);



        } catch (\Exception $e) {
            Log::error('📧 [EnviarCitaC4CJob] ❌ ERROR ENVIANDO EMAIL DE CONFIRMACIÓN', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email ?? 'N/A',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // No lanzar excepción para no interrumpir el proceso de sincronización
            // Solo registrar el error
        }
    }
}
