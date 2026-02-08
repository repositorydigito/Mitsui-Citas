<?php

namespace App\Jobs;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Job para actualizar estados SAP de citas confirmadas
 * 
 * Consulta SAP (Z3PF_GETDATOSASESORPROCESO) para obtener:
 * - PE_FEC_ULT_SERV: Determina estado "En Trabajo"
 * - PE_FEC_FACTURA: Determina estado "Trabajo Concluido"
 * 
 * Se ejecuta cada hora para citas con estado frontend "cita_confirmada"
 */
class UpdateAppointmentSapStatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Timeout del job en segundos (15 minutos)
     */
    public $timeout = 900;

    /**
     * Número de intentos
     */
    public $tries = 3;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('[UpdateAppointmentSapStatesJob] === INICIANDO ACTUALIZACIÓN DE ESTADOS SAP ===');

        try {
            // Verificar si el webservice está habilitado
            if (!config('vehiculos_webservice.enabled', true)) {
                Log::info('[UpdateAppointmentSapStatesJob] Webservice SAP deshabilitado, saltando actualización');
                return;
            }

            // Obtener citas que necesitan actualización
            $appointments = $this->obtenerCitasParaActualizar();

            if ($appointments->isEmpty()) {
                Log::info('[UpdateAppointmentSapStatesJob] No hay citas para actualizar');
                return;
            }

            Log::info('[UpdateAppointmentSapStatesJob] Citas a actualizar: ' . $appointments->count());

            // Crear cliente SOAP
            $soapClient = $this->crearClienteSAP();
            if (!$soapClient) {
                Log::error('[UpdateAppointmentSapStatesJob] No se pudo crear cliente SOAP');
                return;
            }

            $actualizadas = 0;
            $errores = 0;

            // Procesar cada cita
            foreach ($appointments as $appointment) {
                try {
                    $this->actualizarEstadosCita($appointment, $soapClient);
                    $actualizadas++;
                } catch (\Exception $e) {
                    $errores++;
                    Log::error('[UpdateAppointmentSapStatesJob] Error al actualizar cita ' . $appointment->id, [
                        'error' => $e->getMessage(),
                        'placa' => $appointment->vehicle_plate
                    ]);
                }

                // Pequeña pausa para no saturar SAP
                usleep(100000); // 0.1 segundos
            }

            Log::info('[UpdateAppointmentSapStatesJob] === ACTUALIZACIÓN COMPLETADA ===', [
                'total' => $appointments->count(),
                'actualizadas' => $actualizadas,
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            Log::error('[UpdateAppointmentSapStatesJob] Error crítico en job: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener citas que necesitan actualización de estados SAP
     * 
     * Criterios:
     * - Estado frontend "cita_confirmada" activo
     * - Fecha de cita >= hoy
     * - No canceladas
     */
    protected function obtenerCitasParaActualizar()
    {
        return Appointment::where('status', '!=', 'cancelled')
            ->where('appointment_date', '>=', now()->startOfDay())
            ->whereNotNull('vehicle_plate')
            ->whereNotNull('frontend_states')
            ->whereRaw("JSON_EXTRACT(frontend_states, '$.cita_confirmada.activo') = true OR JSON_EXTRACT(frontend_states, '$.cita_confirmada.completado') = true")
            ->get();
    }

    /**
     * Crear cliente SOAP para consultar SAP
     */
    protected function crearClienteSAP(): ?SoapClient
    {
        try {
            $wsdlUrl = storage_path('wsdl/vehiculos.wsdl');
            $usuario = config('services.sap_3p.usuario');
            $password = config('services.sap_3p.password');

            if (!file_exists($wsdlUrl)) {
                Log::error('[UpdateAppointmentSapStatesJob] WSDL no encontrado: ' . $wsdlUrl);
                return null;
            }

            $options = [
                'login' => $usuario,
                'password' => $password,
                'connection_timeout' => 10,
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
            ];

            return new SoapClient($wsdlUrl, $options);

        } catch (\Exception $e) {
            Log::error('[UpdateAppointmentSapStatesJob] Error al crear cliente SOAP: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualizar estados SAP de una cita específica
     */
    protected function actualizarEstadosCita(Appointment $appointment, SoapClient $soapClient): void
    {
        $placa = $appointment->vehicle_plate;

        Log::info('[UpdateAppointmentSapStatesJob] Consultando SAP para cita', [
            'appointment_id' => $appointment->id,
            'placa' => $placa
        ]);

        try {
            // Consultar datos del asesor SAP
            $parametros = ['PI_PLACA' => $placa];
            $respuesta = $soapClient->Z3PF_GETDATOSASESORPROCESO($parametros);

            // Extraer XML raw para campos que PHP no parsea bien
            $xmlResponse = $soapClient->__getLastResponse();

            // Extraer fechas SAP
            $fechaUltServ = $respuesta->PE_FEC_ULT_SERV ?? '';
            $fechaFactura = $respuesta->PE_FEC_FACTURA ?? '';

            // Extraer PE_FEC_ULT_SERV del XML si no está en el objeto
            if (empty($fechaUltServ) && !empty($xmlResponse)) {
                if (preg_match('/<PE_FEC_ULT_SERV[^>]*>([^<]*)<\/PE_FEC_ULT_SERV>/', $xmlResponse, $matches)) {
                    $fechaUltServ = trim($matches[1]);
                }
            }

            // Validar fechas
            $fechaUltServValida = !empty($fechaUltServ) && $fechaUltServ !== '0000-00-00';
            $fechaFacturaValida = !empty($fechaFactura) && $fechaFactura !== '0000-00-00';

            // Actualizar campos SAP en la cita
            $appointment->sap_fecha_ult_serv = $fechaUltServValida ? $fechaUltServ : null;
            $appointment->sap_fecha_factura = $fechaFacturaValida ? $fechaFactura : null;
            $appointment->sap_last_check_at = now();

            // Calcular y actualizar estados frontend
            $this->actualizarEstadosFrontend($appointment);

            $appointment->save();

            Log::info('[UpdateAppointmentSapStatesJob] Cita actualizada exitosamente', [
                'appointment_id' => $appointment->id,
                'placa' => $placa,
                'fecha_ult_serv' => $fechaUltServ,
                'fecha_factura' => $fechaFactura,
                'frontend_states' => $appointment->frontend_states
            ]);

        } catch (SoapFault $e) {
            Log::warning('[UpdateAppointmentSapStatesJob] Error SOAP al consultar placa ' . $placa . ': ' . $e->getMessage());
            
            // Marcar como consultado aunque haya fallado para no reintentar constantemente
            $appointment->sap_last_check_at = now();
            $appointment->save();
            
            throw $e;
        }
    }

    /**
     * Actualizar estados frontend basados en datos SAP
     * 
     * Lógica:
     * - Trabajo Concluido: si fecha_factura >= fecha_cita
     * - En Trabajo: si fecha_ult_serv == fecha_cita
     * - Cita Confirmada: si no cumple ninguna anterior
     */
    protected function actualizarEstadosFrontend(Appointment $appointment): void
    {
        $fechaCita = $appointment->appointment_date->format('Y-m-d');
        $fechaUltServ = $appointment->sap_fecha_ult_serv;
        $fechaFactura = $appointment->sap_fecha_factura;

        // Obtener estados actuales o inicializar
        $frontendStates = $appointment->frontend_states ?? [
            'cita_confirmada' => ['activo' => true, 'completado' => true],
            'en_trabajo' => ['activo' => false, 'completado' => false],
            'trabajo_concluido' => ['activo' => false, 'completado' => false],
        ];

        // CASO 1: TRABAJO CONCLUIDO - Si tiene fecha de factura >= fecha de cita
        if ($fechaFactura && $fechaFactura >= $fechaCita) {
            $frontendStates['cita_confirmada'] = ['activo' => false, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => false, 'completado' => true];
            $frontendStates['trabajo_concluido'] = ['activo' => true, 'completado' => true];

            Log::info('[UpdateAppointmentSapStatesJob] Estado: TRABAJO CONCLUIDO', [
                'appointment_id' => $appointment->id,
                'fecha_factura' => $fechaFactura,
                'fecha_cita' => $fechaCita
            ]);
        }
        // CASO 2: EN TRABAJO - Si tiene fecha de servicio == fecha de cita
        elseif ($fechaUltServ && $fechaUltServ === $fechaCita) {
            $frontendStates['cita_confirmada'] = ['activo' => false, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => true, 'completado' => false];
            $frontendStates['trabajo_concluido'] = ['activo' => false, 'completado' => false];

            Log::info('[UpdateAppointmentSapStatesJob] Estado: EN TRABAJO', [
                'appointment_id' => $appointment->id,
                'fecha_ult_serv' => $fechaUltServ,
                'fecha_cita' => $fechaCita
            ]);
        }
        // CASO 3: CITA CONFIRMADA - No cumple condiciones anteriores
        else {
            $frontendStates['cita_confirmada'] = ['activo' => true, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => false, 'completado' => false];
            $frontendStates['trabajo_concluido'] = ['activo' => false, 'completado' => false];

            Log::info('[UpdateAppointmentSapStatesJob] Estado: CITA CONFIRMADA', [
                'appointment_id' => $appointment->id,
                'fecha_ult_serv' => $fechaUltServ,
                'fecha_factura' => $fechaFactura,
                'fecha_cita' => $fechaCita
            ]);
        }

        $appointment->frontend_states = $frontendStates;
    }
}
