<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use SoapClient;

class PreloadVehiclesByRuc extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'preload:vehicles
                            {ruc : RUC del cliente}
                            {--chunk-size=100 : Cantidad de vehículos a procesar por bloque}
                            {--skip-c4c : Omitir consulta a C4C (más rápido)}';

    /**
     * The console command description.
     */
    protected $description = 'Pre-cargar vehículos de SAP a BD en bloques (sin modificar código existente)';

    protected $wsdlUrl;
    protected $usuario;
    protected $password;
    protected $timeout = 30;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ruc = $this->argument('ruc');
        $chunkSize = (int) $this->option('chunk-size');
        $skipC4c = $this->option('skip-c4c');

        $this->newLine();
        $this->info("🚀 PRE-CARGA DE VEHÍCULOS PARA RUC: {$ruc}");
        $this->info("📦 Tamaño de bloque: {$chunkSize} vehículos");
        $this->info("⚙️  Consulta C4C: " . ($skipC4c ? 'OMITIDA (más rápido)' : 'ACTIVA'));
        $this->newLine();

        // Paso 1: Buscar o crear usuario
        $user = $this->obtenerUsuario($ruc);
        if (!$user) {
            $this->error("❌ No se pudo obtener el usuario para RUC: {$ruc}");
            return 1;
        }

        $this->info("✅ Usuario encontrado: {$user->name} (ID: {$user->id})");

        // Paso 2: Configurar SAP
        if (!$this->configurarSAP()) {
            return 1;
        }

        // Paso 3: Consultar vehículos de SAP por marca
        $marcas = [
            'Z01' => 'TOYOTA',
            'Z02' => 'LEXUS',
            'Z03' => 'HINO',
        ];

        $totalVehiculos = 0;
        $totalPersistidos = 0;

        foreach ($marcas as $codigo => $nombre) {
            $this->newLine();
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("🚗 Procesando marca: {$nombre} ({$codigo})");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            try {
                // Obtener vehículos de SAP
                $vehiculos = $this->consultarSAP($ruc, $codigo);

                if (empty($vehiculos)) {
                    $this->comment("   ⚪ No hay vehículos de {$nombre}");
                    continue;
                }

                $count = count($vehiculos);
                $totalVehiculos += $count;
                $this->info("   📊 Total obtenidos de SAP: {$count} vehículos");

                // Persistir en bloques
                $persistidos = $this->persistirEnBloques($vehiculos, $user, $codigo, $nombre, $chunkSize, $skipC4c);
                $totalPersistidos += $persistidos;

                $this->info("   ✅ {$nombre}: {$persistidos}/{$count} vehículos guardados");

            } catch (\Exception $e) {
                $this->error("   ❌ Error en {$nombre}: " . $e->getMessage());
                Log::error("[PreloadVehicles] Error en marca {$codigo}", [
                    'ruc' => $ruc,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Resumen final
        $this->newLine();
        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
        $this->line("<fg=green;options=bold>🎯 RESUMEN DE PRE-CARGA</>");
        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
        $this->line("<fg=white>RUC:</> <fg=yellow>{$ruc}</>");
        $this->line("<fg=white>Usuario:</> <fg=yellow>{$user->name}</>");
        $this->line("<fg=white>Vehículos obtenidos de SAP:</> <fg=cyan>{$totalVehiculos}</>");
        $this->line("<fg=white>Vehículos guardados en BD:</> <fg=green;options=bold>{$totalPersistidos}</>");
        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
        $this->newLine();

        if ($totalPersistidos > 0) {
            $this->line("<fg=green;options=bold>✅ Pre-carga completada exitosamente!</>");
            $this->line("<fg=gray>💡 Ahora cuando el usuario se logee, los vehículos cargarán desde BD instantáneamente.</>");
        }

        return 0;
    }

    /**
     * Obtener o buscar usuario por RUC
     */
    protected function obtenerUsuario(string $ruc): ?User
    {
        $user = User::where('document_number', $ruc)->first();

        if (!$user) {
            $this->warn("⚠️  Usuario no encontrado en BD, buscando...");
            // Aquí podrías crear lógica para buscar en C4C si es necesario
            // Por ahora, solo devolvemos null
            return null;
        }

        return $user;
    }

    /**
     * Configurar credenciales SAP
     */
    protected function configurarSAP(): bool
    {
        $this->wsdlUrl = storage_path('wsdl/vehiculos.wsdl');
        $this->usuario = config('services.sap_3p.usuario');
        $this->password = config('services.sap_3p.password');

        if (!file_exists($this->wsdlUrl)) {
            $this->error("❌ WSDL no encontrado: {$this->wsdlUrl}");
            return false;
        }

        if (empty($this->usuario) || empty($this->password)) {
            $this->error("❌ Credenciales SAP no configuradas");
            return false;
        }

        $this->info("✅ Configuración SAP validada");
        return true;
    }

    /**
     * Consultar vehículos desde SAP
     */
    protected function consultarSAP(string $ruc, string $marca): array
    {
        $opciones = [
            'trace' => false,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => $this->timeout,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]),
            'login' => $this->usuario,
            'password' => $this->password,
        ];

        $cliente = new SoapClient($this->wsdlUrl, $opciones);

        $parametros = [
            'PI_NUMDOCCLI' => $ruc,
            'PI_MARCA' => $marca,
        ];

        // Mostrar mensaje mientras consulta
        $this->line("   <fg=cyan>🔄 Consultando SAP...</>");
        $startTime = microtime(true);

        $respuesta = $cliente->Z3PF_GETLISTAVEHICULOS($parametros);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->line("   <fg=green>✓</> Consulta completada en <fg=yellow>{$elapsed}s</>");

        // Procesar respuesta
        $vehiculos = [];
        if (isset($respuesta->TT_LISVEH->item)) {
            $items = is_array($respuesta->TT_LISVEH->item)
                ? $respuesta->TT_LISVEH->item
                : [$respuesta->TT_LISVEH->item];

            foreach ($items as $item) {
                $vehiculos[] = [
                    'vehicle_id' => (string) ($item->VHCLE ?? $item->VHCLIE ?? ''),
                    'license_plate' => (string) ($item->NUMPLA ?? ''),
                    'model' => (string) ($item->MODVER ?? ''),
                    'year' => (string) ($item->ANIOMOD ?? ''),
                    'engine_number' => (string) ($item->NUMMOT ?? ''),
                    'brand_code' => $marca,
                ];
            }
        }

        return $vehiculos;
    }

    /**
     * Persistir vehículos en bloques (chunks)
     */
    protected function persistirEnBloques(array $vehiculos, User $user, string $brandCode, string $brandName, int $chunkSize, bool $skipC4c): int
    {
        $chunks = array_chunk($vehiculos, $chunkSize);
        $totalChunks = count($chunks);
        $persistidos = 0;
        $conflictos = 0;

        $this->line("   <fg=cyan>📦 Procesando en {$totalChunks} bloques de hasta {$chunkSize} vehículos...</>");
        $this->newLine();

        $bar = $this->output->createProgressBar(count($vehiculos));
        $bar->setFormat(
            "   <fg=white>%current%/%max%</> [<fg=green>%bar%</>] <fg=yellow>%percent:3s%%</>\n" .
            "   <fg=cyan>⏱ Tiempo:</> %elapsed:6s% <fg=cyan>│</> <fg=magenta>Restante:</> %estimated:-6s% <fg=cyan>│</> %message%"
        );
        $bar->setBarCharacter('<fg=green>█</>');
        $bar->setEmptyBarCharacter('<fg=gray>░</>');
        $bar->setProgressCharacter('<fg=green>▶</>');
        $bar->setMessage('<fg=green>Iniciando...</>');
        $bar->start();

        foreach ($chunks as $chunkIndex => $chunk) {
            $chunkNum = $chunkIndex + 1;
            $bar->setMessage("<fg=cyan>Bloque {$chunkNum}/{$totalChunks}</> <fg=white>│</> <fg=yellow>Guardados: {$persistidos}</>");

            try {
                // Procesar chunk en transacción
                DB::transaction(function () use ($chunk, $user, $brandCode, $brandName, &$persistidos, &$conflictos, $bar) {
                    foreach ($chunk as $vehiculoData) {
                        if (empty($vehiculoData['vehicle_id']) || empty($vehiculoData['license_plate'])) {
                            continue;
                        }

                        // Buscar vehículo existente por vehicle_id Y user_id (para no robar vehículos de otros clientes)
                        $existente = Vehicle::where('vehicle_id', $vehiculoData['vehicle_id'])
                            ->where('user_id', $user->id)
                            ->first();

                        // SEGURIDAD: Verificar si el vehicle_id existe para OTRO usuario (conflicto de datos)
                        $conflicto = Vehicle::where('vehicle_id', $vehiculoData['vehicle_id'])
                            ->where('user_id', '!=', $user->id)
                            ->first();

                        if ($conflicto) {
                            Log::warning("[PreloadVehicles] CONFLICTO: vehicle_id {$vehiculoData['vehicle_id']} ya pertenece a user_id {$conflicto->user_id}, se omite", [
                                'ruc_solicitado' => $user->document_number,
                                'user_id_solicitado' => $user->id,
                                'user_id_propietario' => $conflicto->user_id,
                                'vehicle_id' => $vehiculoData['vehicle_id'],
                            ]);
                            $conflictos++;
                            $bar->advance();
                            continue; // Saltar este vehículo
                        }

                        $datosVehiculo = [
                            'vehicle_id' => $vehiculoData['vehicle_id'],
                            'license_plate' => $vehiculoData['license_plate'],
                            'model' => $vehiculoData['model'],
                            'year' => $vehiculoData['year'],
                            'engine_number' => $vehiculoData['engine_number'],
                            'brand_code' => $brandCode,
                            'brand_name' => $brandName,
                            'user_id' => $user->id,
                            'status' => 'active',
                        ];

                        if ($existente) {
                            // Actualizar
                            $existente->update($datosVehiculo);
                        } else {
                            // Crear nuevo
                            Vehicle::create($datosVehiculo);
                        }

                        $persistidos++;
                        $bar->advance();
                    }
                });

                // Pequeña pausa entre chunks para no saturar MySQL
                usleep(50000); // 50ms

            } catch (\Exception $e) {
                $bar->setMessage("<fg=red>✗ Error en bloque {$chunkNum}</>");
                Log::error("[PreloadVehicles] Error en chunk {$chunkNum}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        $bar->setMessage('<fg=green>✓ Completado</>');
        $bar->finish();
        $this->newLine(2);

        // Mostrar resumen de conflictos si los hubo
        if ($conflictos > 0) {
            $this->warn("   ⚠️  Se omitieron {$conflictos} vehículos por conflictos (ya pertenecen a otros usuarios)");
        }

        return $persistidos;
    }
}
