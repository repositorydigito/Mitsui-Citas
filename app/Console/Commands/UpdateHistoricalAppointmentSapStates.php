<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SoapClient;
use SoapFault;

/**
 * Comando para actualizar estados SAP de citas históricas (incluyendo pasadas)
 * 
 * Este comando se ejecuta UNA SOLA VEZ para sincronizar citas antiguas.
 * Después de esto, el job programado mantendrá actualizadas solo las citas futuras.
 */
class UpdateHistoricalAppointmentSapStates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:update-historical-sap-states 
                            {--limit=100 : Número máximo de citas a procesar}
                            {--force : Procesar sin confirmación}';

    /**
     * The console command description.
     */
    protected $description = 'Actualizar estados SAP de todas las citas confirmadas (incluyendo pasadas) - Ejecutar UNA VEZ';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ACTUALIZACIÓN HISTÓRICA DE ESTADOS SAP ===');
        $this->newLine();

        // Verificar si el webservice está habilitado
        if (!config('vehiculos_webservice.enabled', true)) {
            $this->error('Webservice SAP deshabilitado en configuración');
            return 1;
        }

        // Obtener citas a procesar (SIN filtro de fecha)
        $appointments = $this->obtenerCitasHistoricas();

        if ($appointments->isEmpty()) {
            $this->info('No hay citas para actualizar');
            return 0;
        }

        $this->info("Citas encontradas: {$appointments->count()}");
        $this->newLine();

        // Confirmar antes de procesar
        if (!$this->option('force')) {
            if (!$this->confirm('¿Deseas continuar con la actualización?')) {
                $this->info('Operación cancelada');
                return 0;
            }
        }

        // Crear cliente SOAP
        $soapClient = $this->crearClienteSAP();
        if (!$soapClient) {
            $this->error('No se pudo crear cliente SOAP');
            return 1;
        }

        // Procesar citas con barra de progreso
        $this->info('Procesando citas...');
        $bar = $this->output->createProgressBar($appointments->count());
        $bar->start();

        $actualizadas = 0;
        $errores = 0;

        foreach ($appointments as $appointment) {
            try {
                $this->actualizarEstadosCita($appointment, $soapClient);
                $actualizadas++;
            } catch (\Exception $e) {
                $errores++;
                Log::error('[UpdateHistoricalSapStates] Error en cita ' . $appointment->id, [
                    'error' => $e->getMessage(),
                    'placa' => $appointment->vehicle_plate
                ]);
            }

            $bar->advance();

            // Pausa para no saturar SAP
            usleep(100000); // 0.1 segundos
        }

        $bar->finish();
        $this->newLine(2);

        // Resumen
        $this->info('=== RESUMEN ===');
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Total procesadas', $appointments->count()],
                ['Actualizadas exitosamente', $actualizadas],
                ['Errores', $errores],
            ]
        );

        $this->newLine();
        $this->info('✅ Actualización histórica completada');
        $this->info('💡 A partir de ahora, el job programado mantendrá actualizadas solo las citas futuras');

        return 0;
    }

    /**
     * Obtener citas históricas para actualizar (SIN filtro de fecha)
     */
    protected function obtenerCitasHistoricas()
    {
        $limit = (int) $this->option('limit');

        $query = Appointment::where('status', '!=', 'cancelled')
            ->whereNotNull('vehicle_plate')
            ->whereNotNull('frontend_states')
            ->whereRaw("JSON_EXTRACT(frontend_states, '$.cita_confirmada.activo') = true OR JSON_EXTRACT(frontend_states, '$.cita_confirmada.completado') = true")
            ->orderBy('appointment_date', 'desc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Crear cliente SOAP
     */
    protected function crearClienteSAP(): ?SoapClient
    {
        try {
            $wsdlUrl = storage_path('wsdl/vehiculos.wsdl');
            $usuario = config('services.sap_3p.usuario');
            $password = config('services.sap_3p.password');

            if (!file_exists($wsdlUrl)) {
                $this->error('WSDL no encontrado: ' . $wsdlUrl);
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
            $this->error('Error al crear cliente SOAP: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualizar estados SAP de una cita
     */
    protected function actualizarEstadosCita(Appointment $appointment, SoapClient $soapClient): void
    {
        $placa = $appointment->vehicle_plate;

        try {
            // Consultar SAP
            $parametros = ['PI_PLACA' => $placa];
            $respuesta = $soapClient->Z3PF_GETDATOSASESORPROCESO($parametros);
            $xmlResponse = $soapClient->__getLastResponse();

            // Extraer fechas
            $fechaUltServ = $respuesta->PE_FEC_ULT_SERV ?? '';
            $fechaFactura = $respuesta->PE_FEC_FACTURA ?? '';

            // Extraer del XML si no está en objeto
            if (empty($fechaUltServ) && !empty($xmlResponse)) {
                if (preg_match('/<PE_FEC_ULT_SERV[^>]*>([^<]*)<\/PE_FEC_ULT_SERV>/', $xmlResponse, $matches)) {
                    $fechaUltServ = trim($matches[1]);
                }
            }

            // Validar fechas
            $fechaUltServValida = !empty($fechaUltServ) && $fechaUltServ !== '0000-00-00';
            $fechaFacturaValida = !empty($fechaFactura) && $fechaFactura !== '0000-00-00';

            // Actualizar campos
            $appointment->sap_fecha_ult_serv = $fechaUltServValida ? $fechaUltServ : null;
            $appointment->sap_fecha_factura = $fechaFacturaValida ? $fechaFactura : null;
            $appointment->sap_last_check_at = now();

            // Recalcular estados frontend
            $this->actualizarEstadosFrontend($appointment);

            $appointment->save();

        } catch (SoapFault $e) {
            // Marcar como consultado aunque falle
            $appointment->sap_last_check_at = now();
            $appointment->save();
            
            throw $e;
        }
    }

    /**
     * Actualizar estados frontend basados en datos SAP
     */
    protected function actualizarEstadosFrontend(Appointment $appointment): void
    {
        $fechaCita = $appointment->appointment_date->format('Y-m-d');
        $fechaUltServ = $appointment->sap_fecha_ult_serv;
        $fechaFactura = $appointment->sap_fecha_factura;

        $frontendStates = $appointment->frontend_states ?? [
            'cita_confirmada' => ['activo' => true, 'completado' => true],
            'en_trabajo' => ['activo' => false, 'completado' => false],
            'trabajo_concluido' => ['activo' => false, 'completado' => false],
        ];

        // CASO 1: TRABAJO CONCLUIDO
        if ($fechaFactura && $fechaFactura >= $fechaCita) {
            $frontendStates['cita_confirmada'] = ['activo' => false, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => false, 'completado' => true];
            $frontendStates['trabajo_concluido'] = ['activo' => true, 'completado' => true];
        }
        // CASO 2: EN TRABAJO
        elseif ($fechaUltServ && $fechaUltServ === $fechaCita) {
            $frontendStates['cita_confirmada'] = ['activo' => false, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => true, 'completado' => false];
            $frontendStates['trabajo_concluido'] = ['activo' => false, 'completado' => false];
        }
        // CASO 3: CITA CONFIRMADA
        else {
            $frontendStates['cita_confirmada'] = ['activo' => true, 'completado' => true];
            $frontendStates['en_trabajo'] = ['activo' => false, 'completado' => false];
            $frontendStates['trabajo_concluido'] = ['activo' => false, 'completado' => false];
        }

        $appointment->frontend_states = $frontendStates;
    }
}
