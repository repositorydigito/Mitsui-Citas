<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SoapClient;

class CountVehiclesByRuc extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:count-vehicles
                            {documento : RUC o documento del cliente}
                            {--timeout=30 : Timeout en segundos para cada consulta}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Contar vehículos por RUC sin traer todos los datos (optimizado)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documento = $this->argument('documento');
        $timeout = (int) $this->option('timeout');

        $this->newLine();
        $this->info("🔍 Contando vehículos para RUC/Documento: {$documento}");
        $this->info("⏱️  Timeout configurado: {$timeout} segundos por marca");
        $this->newLine();

        // Verificar configuración
        $wsdlUrl = storage_path('wsdl/vehiculos.wsdl');
        $usuario = config('services.sap_3p.usuario');
        $password = config('services.sap_3p.password');

        if (!file_exists($wsdlUrl)) {
            $this->error("❌ WSDL no encontrado en: {$wsdlUrl}");
            return 1;
        }

        if (empty($usuario) || empty($password)) {
            $this->error("❌ Credenciales SAP no configuradas");
            return 1;
        }

        $this->info("✅ Configuración SAP validada");
        $this->newLine();

        // Marcas a consultar
        $marcas = [
            'Z01' => 'TOYOTA',
            'Z02' => 'LEXUS',
            'Z03' => 'HINO',
        ];

        $conteoTotal = 0;
        $resultadosPorMarca = [];

        // Consultar cada marca
        foreach ($marcas as $codigo => $nombre) {
            $this->line("🚗 Consultando {$nombre} ({$codigo})...");

            try {
                $count = $this->contarVehiculosPorMarca($documento, $codigo, $timeout);
                $resultadosPorMarca[$codigo] = [
                    'nombre' => $nombre,
                    'count' => $count,
                    'status' => 'success'
                ];
                $conteoTotal += $count;

                if ($count > 0) {
                    $this->info("   ✅ {$nombre}: {$count} vehículos");
                } else {
                    $this->line("   ⚪ {$nombre}: 0 vehículos");
                }

            } catch (\Exception $e) {
                $resultadosPorMarca[$codigo] = [
                    'nombre' => $nombre,
                    'count' => 0,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
                $this->error("   ❌ {$nombre}: Error - " . $e->getMessage());
            }
        }

        // Mostrar resumen
        $this->newLine();
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 RESUMEN PARA RUC: {$documento}");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $tableData = [];
        foreach ($resultadosPorMarca as $codigo => $resultado) {
            $tableData[] = [
                'Código' => $codigo,
                'Marca' => $resultado['nombre'],
                'Vehículos' => $resultado['count'],
                'Estado' => $resultado['status'] === 'success' ? '✅' : '❌'
            ];
        }

        $this->table(
            ['Código', 'Marca', 'Vehículos', 'Estado'],
            $tableData
        );

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🎯 TOTAL: {$conteoTotal} vehículos");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->newLine();

        // Advertencia si hay muchos vehículos
        if ($conteoTotal > 500) {
            $this->warn("⚠️  ADVERTENCIA: Este cliente tiene {$conteoTotal} vehículos.");
            $this->warn("   El sistema puede experimentar timeout al intentar cargarlos todos.");
            $this->warn("   Se recomienda implementar paginación o lazy loading.");
        } elseif ($conteoTotal > 100) {
            $this->comment("💡 Este cliente tiene {$conteoTotal} vehículos.");
            $this->comment("   Considera optimizar la carga si experimentas lentitud.");
        }

        return 0;
    }

    /**
     * Contar vehículos por marca sin procesar todos los datos
     */
    protected function contarVehiculosPorMarca(string $documento, string $marca, int $timeout): int
    {
        $wsdlUrl = storage_path('wsdl/vehiculos.wsdl');
        $usuario = config('services.sap_3p.usuario');
        $password = config('services.sap_3p.password');

        // Configurar cliente SOAP con timeout
        $opciones = [
            'trace' => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $timeout,
            'default_socket_timeout' => $timeout,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
                'http' => [
                    'timeout' => $timeout,
                ],
            ]),
            'login' => $usuario,
            'password' => $password,
        ];

        $cliente = new SoapClient($wsdlUrl, $opciones);

        // Parámetros para SAP
        $parametros = [
            'PI_NUMDOCCLI' => $documento,
            'PI_MARCA' => $marca,
        ];

        $startTime = microtime(true);

        // Realizar consulta SOAP
        $respuesta = $cliente->Z3PF_GETLISTAVEHICULOS($parametros);

        $elapsedTime = round(microtime(true) - $startTime, 2);

        // Contar vehículos sin procesarlos todos
        $count = 0;
        if (isset($respuesta->TT_LISVEH->item)) {
            if (is_array($respuesta->TT_LISVEH->item)) {
                $count = count($respuesta->TT_LISVEH->item);
            } else {
                // Un solo vehículo
                $count = 1;
            }
        }

        // Log del tiempo de ejecución
        $this->line("   ⏱️  Consulta completada en {$elapsedTime}s");

        return $count;
    }
}
