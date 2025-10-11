<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\C4C\VehicleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateVehiclesByRuc extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'update:vehicles-by-ruc
                            {ruc : RUC del cliente}
                            {--dry-run : Solo mostrar qué se actualizaría sin hacer cambios}
                            {--force : Forzar actualización incluso si ya tiene valor}
                            {--chunk-size=50 : Cantidad de vehículos a procesar por bloque}';

    /**
     * The console command description.
     */
    protected $description = 'Actualizar tipo_valor_trabajo de todos los vehículos de un RUC específico consultando C4C';

    protected VehicleService $vehicleService;

    public function __construct(VehicleService $vehicleService)
    {
        parent::__construct();
        $this->vehicleService = $vehicleService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ruc = $this->argument('ruc');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $chunkSize = (int) $this->option('chunk-size');

        $this->newLine();
        $this->info("🚀 ACTUALIZACIÓN DE TIPO VALOR TRABAJO PARA RUC: {$ruc}");
        $this->info("📦 Tamaño de bloque: {$chunkSize} vehículos");
        $this->info("⚙️  Modo: " . ($dryRun ? 'DRY-RUN (solo vista previa)' : 'ACTUALIZACIÓN REAL'));
        $this->info("🔄 Forzar actualización: " . ($force ? 'SÍ' : 'NO'));
        $this->newLine();

        if ($dryRun) {
            $this->warn('⚠️  MODO DRY-RUN: No se realizarán cambios reales en la base de datos');
            $this->newLine();
        }

        // Paso 1: Buscar usuario por RUC
        $user = $this->obtenerUsuario($ruc);
        if (!$user) {
            $this->error("❌ No se encontró usuario con RUC: {$ruc}");
            return 1;
        }

        $this->info("✅ Usuario encontrado: {$user->name} (ID: {$user->id})");

        // Paso 2: Obtener vehículos del usuario
        $vehiculos = Vehicle::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        if ($vehiculos->isEmpty()) {
            $this->warn("⚠️  No se encontraron vehículos activos para el RUC: {$ruc}");
            return 1;
        }

        $totalVehiculos = $vehiculos->count();
        $this->info("📊 Total de vehículos a procesar: {$totalVehiculos}");
        $this->newLine();

        // Paso 3: Procesar vehículos en bloques
        $resultado = $this->procesarVehiculosEnBloques($vehiculos, $chunkSize, $dryRun, $force, $user);

        // Paso 4: Mostrar resumen final
        $this->mostrarResumenFinal($resultado, $ruc, $user->name, $dryRun);

        return 0;
    }

    /**
     * Obtener usuario por RUC
     */
    protected function obtenerUsuario(string $ruc): ?User
    {
        return User::where('document_number', $ruc)->first();
    }

    /**
     * Procesar vehículos en bloques para optimizar rendimiento
     */
    protected function procesarVehiculosEnBloques($vehiculos, int $chunkSize, bool $dryRun, bool $force, User $user): array
    {
        $chunks = $vehiculos->chunk($chunkSize);
        $totalChunks = $chunks->count();
        
        $estadisticas = [
            'procesados' => 0,
            'actualizados' => 0,
            'ya_actualizados' => 0,
            'no_encontrados' => 0,
            'errores' => 0,
        ];

        $this->line("📦 Procesando en {$totalChunks} bloques de hasta {$chunkSize} vehículos...");
        $this->newLine();

        $bar = $this->output->createProgressBar($vehiculos->count());
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
            $bar->setMessage("<fg=cyan>Bloque {$chunkNum}/{$totalChunks}</> <fg=white>│</> <fg=yellow>Actualizados: {$estadisticas['actualizados']}</>");

            foreach ($chunk as $vehiculo) {
                $estadisticas['procesados']++;

                try {
                    // Consultar tipo_valor_trabajo desde C4C
                    $tipoValorTrabajo = $this->vehicleService->obtenerTipoValorTrabajoPorPlaca($vehiculo->license_plate);

                    if ($tipoValorTrabajo) {
                        // Verificar si necesita actualización
                        $necesitaActualizacion = $force || empty($vehiculo->tipo_valor_trabajo) || 
                                               $vehiculo->tipo_valor_trabajo !== $tipoValorTrabajo;

                        if ($necesitaActualizacion) {
                            if (!$dryRun) {
                                $vehiculo->tipo_valor_trabajo = $tipoValorTrabajo;
                                $vehiculo->save();
                                
                                Log::info("[UpdateVehiclesByRuc] Actualizado tipo_valor_trabajo", [
                                    'ruc' => $user->document_number,
                                    'vehiculo_id' => $vehiculo->id,
                                    'placa' => $vehiculo->license_plate,
                                    'valor_anterior' => $vehiculo->getOriginal('tipo_valor_trabajo'),
                                    'valor_nuevo' => $tipoValorTrabajo,
                                ]);
                            }
                            $estadisticas['actualizados']++;
                        } else {
                            $estadisticas['ya_actualizados']++;
                        }
                    } else {
                        $estadisticas['no_encontrados']++;
                        
                        Log::warning("[UpdateVehiclesByRuc] No se encontró tipo_valor_trabajo en C4C", [
                            'ruc' => $user->document_number,
                            'vehiculo_id' => $vehiculo->id,
                            'placa' => $vehiculo->license_plate,
                        ]);
                    }

                } catch (\Exception $e) {
                    $estadisticas['errores']++;
                    
                    Log::error("[UpdateVehiclesByRuc] Error procesando vehículo", [
                        'ruc' => $user->document_number,
                        'vehiculo_id' => $vehiculo->id,
                        'placa' => $vehiculo->license_plate,
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
                
                // Pausa pequeña para no saturar el webservice C4C
                usleep(100000); // 0.1 segundos
            }

            // Pausa entre chunks
            if ($chunkIndex < $totalChunks - 1) {
                usleep(200000); // 0.2 segundos entre bloques
            }
        }

        $bar->setMessage('<fg=green>✓ Completado</>');
        $bar->finish();
        $this->newLine(2);

        return $estadisticas;
    }

    /**
     * Mostrar resumen final detallado
     */
    protected function mostrarResumenFinal(array $estadisticas, string $ruc, string $userName, bool $dryRun): void
    {
        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
        $this->line("<fg=green;options=bold>🎯 RESUMEN DE ACTUALIZACIÓN TIPO VALOR TRABAJO</>");
        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");
        
        $this->line("<fg=white>Cliente:</> <fg=yellow>{$userName}</>");
        $this->line("<fg=white>RUC:</> <fg=yellow>{$ruc}</>");
        $this->line("<fg=white>Modo:</> <fg=yellow>" . ($dryRun ? 'DRY-RUN (vista previa)' : 'ACTUALIZACIÓN REAL') . "</>");
        $this->newLine();

        // Tabla de estadísticas
        $this->table(
            ['Métrica', 'Cantidad', 'Descripción'],
            [
                [
                    'Total procesados', 
                    $estadisticas['procesados'], 
                    'Vehículos consultados en C4C'
                ],
                [
                    $dryRun ? 'Se actualizarían' : 'Actualizados', 
                    $estadisticas['actualizados'], 
                    'Tipo valor trabajo actualizado'
                ],
                [
                    'Ya actualizados', 
                    $estadisticas['ya_actualizados'], 
                    'Ya tenían el valor correcto'
                ],
                [
                    'No encontrados', 
                    $estadisticas['no_encontrados'], 
                    'No encontrados en C4C'
                ],
                [
                    'Errores', 
                    $estadisticas['errores'], 
                    'Errores durante el proceso'
                ],
            ]
        );

        $this->line("<fg=cyan>━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━</>");

        // Mensaje final
        if ($estadisticas['actualizados'] > 0) {
            if ($dryRun) {
                $this->line("<fg=yellow;options=bold>ℹ️  Se actualizarían {$estadisticas['actualizados']} vehículos</>");
                $this->line("<fg=gray>💡 Ejecutar sin --dry-run para aplicar los cambios realmente.</>");
            } else {
                $this->line("<fg=green;options=bold>✅ Proceso completado exitosamente!</>");
                $this->line("<fg=gray>💡 {$estadisticas['actualizados']} vehículos actualizados con tipo_valor_trabajo desde C4C.</>");
            }
        } else {
            $this->line("<fg=blue;options=bold>ℹ️  No se requirieron actualizaciones.</>");
            $this->line("<fg=gray>💡 Todos los vehículos ya tenían el tipo_valor_trabajo correcto.</>");
        }

        if ($estadisticas['errores'] > 0) {
            $this->newLine();
            $this->warn("⚠️  Se encontraron {$estadisticas['errores']} errores. Revisa los logs para más detalles.");
        }

        if ($estadisticas['no_encontrados'] > 0) {
            $this->newLine();
            $this->comment("💡 {$estadisticas['no_encontrados']} vehículos no fueron encontrados en C4C. Esto puede ser normal.");
        }

        $this->newLine();
    }
}