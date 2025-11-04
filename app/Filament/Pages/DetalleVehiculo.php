<?php

namespace App\Filament\Pages;

use App\Models\Appointment;
use App\Models\Vehicle;
use App\Jobs\DeleteAppointmentC4CJob;
use App\Services\VehiculoSoapService;
use App\Services\C4C\AppointmentService;
use App\Mail\CitaCancelada;
use Illuminate\Support\Facades\Mail;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SoapClient;
use SoapFault;

class DetalleVehiculo extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Detalle del Vehículo';

    protected static ?string $navigationGroup = '🚗 Vehículos';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = '';

    protected static string $view = 'filament.pages.detalle-vehiculo';

    // Ocultar de la navegación principal ya que se accederá desde la página de vehículos
    protected static bool $shouldRegisterNavigation = false;

    // Datos del vehículo - Se cargan dinámicamente en mount()
    public ?array $vehiculo = [
        'modelo' => 'Cargando...',
        'kilometraje' => 'Cargando...',
        'placa' => 'Cargando...',
    ];

    // Datos de mantenimiento - Se cargan dinámicamente en cargarDatosMantenimiento()
    public array $mantenimiento = [
        'ultimo' => 'Cargando...',
        'fecha' => 'Cargando...',
        'vencimiento' => 'Cargando...',
        'disponibles' => ['Cargando...'],
    ];

    // Citas agendadas - Se cargan dinámicamente en cargarCitasAgendadas()
    public array $citasAgendadas = [];

    // Historial de servicios
    public Collection $historialServicios;

    public int $currentPage = 1;

    public int $perPage = 10;

    // Parámetro de URL para el ID del vehículo
    public ?string $vehiculoId = null;

    // Cliente SOAP para SAP
    protected ?SoapClient $soapClient = null;

    // Indicador de si se están cargando datos desde SAP
    public bool $cargandoDesdeSAP = false;

    // Datos del asesor SAP para enriquecer las citas
    protected ?array $datosAsesorSAP = null;

    public function mount(): void
    {
        // Inicializar con valores por defecto
        $this->inicializarValoresPorDefecto();

        // Obtener el vehiculoId de diferentes fuentes
        $vehiculoId = $this->vehiculoId ?? request()->query('vehiculoId') ?? request()->input('vehiculoId');

        Log::info("[DetalleVehiculo] Parámetro recibido: vehiculoId = " . ($vehiculoId ?? 'NULL'));
        Log::info("[DetalleVehiculo] Property vehiculoId: " . ($this->vehiculoId ?? 'NULL'));
        Log::info("[DetalleVehiculo] Query params: " . json_encode(request()->query()));
        Log::info("[DetalleVehiculo] All request: " . json_encode(request()->all()));

        // Cargar datos del vehículo basados en el ID recibido
        if ($vehiculoId) {
            // Limpiar el ID del vehículo (eliminar espacios)
            $vehiculoId = trim(str_replace(' ', '', $vehiculoId));
            
            // IMPORTANTE: Asignar el vehiculoId limpio a la propiedad para uso posterior
            $this->vehiculoId = $vehiculoId;
            Log::info("[DetalleVehiculo] Cargando datos para vehículo ID (limpio): {$vehiculoId}");

            // Buscar el vehículo en la base de datos - intentar diferentes formas de búsqueda
            Log::info('[DetalleVehiculo] Buscando vehículo en la base de datos con vehicle_id o license_plate');

            // Primero intentamos una búsqueda exacta
            $vehiculo = Vehicle::where('vehicle_id', $vehiculoId)
                ->orWhere('license_plate', $vehiculoId)
                ->first();

            // Si no encontramos, intentamos una búsqueda con LIKE
            if (! $vehiculo) {
                Log::info('[DetalleVehiculo] No se encontró con búsqueda exacta, intentando con LIKE');
                $vehiculo = Vehicle::where('vehicle_id', 'LIKE', "%{$vehiculoId}%")
                    ->orWhere('license_plate', 'LIKE', "%{$vehiculoId}%")
                    ->first();
            }

            if ($vehiculo) {
                // Si encontramos el vehículo, usamos sus datos básicos
                $this->cargarDatosVehiculo($vehiculo);

                // Cargar datos de mantenimiento
                $this->cargarDatosMantenimiento($vehiculo);

                Log::info('[DetalleVehiculo] Vehículo encontrado en la base de datos:', $this->vehiculo);

                // PRIMERO: Cargar datos actualizados desde SAP (incluyendo datos del asesor)
                if (config('vehiculos_webservice.enabled', true)) {
                    Log::info("[DetalleVehiculo] Cargando datos actualizados desde SAP para vehículo: {$vehiculo->license_plate}");
                    $this->cargarDatosVehiculoDesdeSAP($vehiculo->license_plate);
                } else {
                    Log::info('[DetalleVehiculo] SAP deshabilitado, usando solo datos de BD');
                }

                // SEGUNDO: Cargar citas agendadas (ahora con datos SAP disponibles)
                $this->cargarCitasAgendadas($vehiculo->id);
            } else {
                Log::warning("[DetalleVehiculo] No se encontró el vehículo con ID: {$vehiculoId}.");

                // Intentar cargar datos desde SAP si está habilitado
                if (config('vehiculos_webservice.enabled', true)) {
                    Log::info("[DetalleVehiculo] Vehículo no encontrado en BD, intentando cargar desde SAP: {$vehiculoId}");
                    $this->cargarDatosVehiculoDesdeSAP($vehiculoId);
                } else {
                    // Establecer mensaje de error si SAP no está disponible
                    $this->vehiculo = [
                        'modelo' => 'Vehículo no encontrado',
                        'kilometraje' => 'No disponible',
                        'placa' => $vehiculoId,
                        'anio' => 'No disponible',
                        'marca' => 'No disponible',
                        'color' => 'No disponible',
                    ];

                    $this->mantenimiento = [
                        'ultimo' => 'No disponible',
                        'fecha' => 'No disponible',
                        'vencimiento' => 'No disponible',
                        'disponibles' => ['Vehículo no encontrado'],
                    ];

                    $this->citasAgendadas = [];
                }
            }
        } else {
            Log::warning('[DetalleVehiculo] No se proporcionó ID de vehículo.');
            // Inicializar historial vacío solo si no hay vehículo
            $this->historialServicios = collect();
        }
    }

    /**
     * Inicializar valores por defecto
     */
    protected function inicializarValoresPorDefecto(): void
    {
        $this->vehiculo = [
            'modelo' => 'No disponible',
            'kilometraje' => 'No disponible',
            'placa' => 'No disponible',
        ];

        $this->mantenimiento = [
            'ultimo' => 'No disponible',
            'fecha' => 'No disponible',
            'vencimiento' => 'No disponible',
            'disponibles' => ['No disponible'],
        ];

        $this->citasAgendadas = [];

        // Inicializar historial vacío
        $this->historialServicios = collect();
    }

    /**
     * Cargar datos del vehículo desde el modelo
     */
    protected function cargarDatosVehiculo(Vehicle $vehiculo): void
    {
        $this->vehiculo = [
            'id' => $vehiculo->id,
            'vehicle_id' => $vehiculo->vehicle_id,
            'modelo' => $vehiculo->model ?? 'No disponible',
            'kilometraje' => $vehiculo->mileage ? number_format($vehiculo->mileage, 0, '.', ',').' Km' : 'No disponible',
            'placa' => $vehiculo->license_plate ?? 'No disponible',
            'anio' => $vehiculo->year ?? 'No disponible',
            'marca' => $vehiculo->brand_name ?? 'No disponible',
            'color' => $vehiculo->color ?? 'No disponible',
            'vin' => $vehiculo->vin ?? 'No disponible',
            'motor' => $vehiculo->engine_number ?? 'No disponible',
        ];
    }

    /**
     * Cargar datos de mantenimiento del vehículo
     */
    protected function cargarDatosMantenimiento(Vehicle $vehiculo): void
    {
        try {
            Log::info("[DetalleVehiculo] Cargando datos de mantenimiento para vehículo ID: {$vehiculo->id}");

            $this->mantenimiento = [
                'ultimo' => $vehiculo->last_service_mileage ? number_format($vehiculo->last_service_mileage, 0, '.', ',').' Km' : 'No disponible',
                'fecha' => $vehiculo->last_service_date ? $vehiculo->last_service_date->format('d/m/Y') : 'No disponible',
                'vencimiento' => $vehiculo->prepaid_maintenance_expiry ? $vehiculo->prepaid_maintenance_expiry->format('d/m/Y') : 'No disponible',
                'disponibles' => $vehiculo->has_prepaid_maintenance ? [
                    '1 Servicio '.number_format($vehiculo->next_service_mileage ?? 10000, 0, '.', ',').' Km',
                ] : ['No disponible'],
            ];

            Log::info('[DetalleVehiculo] Datos de mantenimiento cargados correctamente');
        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al cargar datos de mantenimiento: '.$e->getMessage());

            // Establecer valores predeterminados en caso de error
            $this->mantenimiento = [
                'ultimo' => 'No disponible',
                'fecha' => 'No disponible',
                'vencimiento' => 'No disponible',
                'disponibles' => ['No disponible'],
            ];
        }
    }

    /**
     * Cargar citas agendadas para el vehículo desde WSCitas (C4C)
     */
    protected function cargarCitasAgendadas(int $vehiculoId): void
    {
        try {
            Log::info("[DetalleVehiculo] Cargando citas agendadas desde WSCitas para vehículo ID: {$vehiculoId}");

            // Obtener el vehículo para conseguir la placa
            $vehiculo = Vehicle::find($vehiculoId);
            if (!$vehiculo) {
                Log::warning("[DetalleVehiculo] No se encontró el vehículo con ID: {$vehiculoId}");
                $this->citasAgendadas = [];
                return;
            }

            $placaVehiculo = $vehiculo->license_plate;
            Log::info("[DetalleVehiculo] Placa del vehículo: {$placaVehiculo}");

            // Obtener el usuario logueado y verificar si tiene datos reales de C4C
            $user = Auth::user();   
            if (!$user || !$user->hasRealC4cData()) {
                Log::warning("[DetalleVehiculo] Usuario no tiene datos C4C válidos (es comodín o sin c4c_internal_id)", [
                    'user_id' => $user ? $user->id : 'N/A',
                    'c4c_internal_id' => $user ? $user->c4c_internal_id : 'N/A',
                    'is_comodin' => $user ? $user->is_comodin : 'N/A',
                    'has_real_c4c_data' => $user ? $user->hasRealC4cData() : 'N/A'
                ]);
                
                // Para clientes comodín, usar citas locales directamente
                $this->cargarCitasLocalesPendientes($vehiculoId);
                return;
            }

            $c4cInternalId = $user->c4c_internal_id;
            Log::info("[DetalleVehiculo] Usuario con datos C4C válidos - c4c_internal_id: {$c4cInternalId}");

            // Consultar WSCitas usando AppointmentService (datos reales)
            $appointmentService = new AppointmentService();

            // Obtener todas las citas pendientes del cliente
            $result = $appointmentService->queryPendingAppointments($c4cInternalId);

            Log::info("[DetalleVehiculo] Respuesta WSCitas:", $result);

            if ($result['success'] && !empty($result['data'])) {
                // LOG DETALLADO: Datos brutos de C4C
                Log::info("[DetalleVehiculo] ===== DATOS BRUTOS DE C4C =====", [
                    'total_citas_c4c' => count($result['data']),
                    'estructura_primera_cita' => !empty($result['data']) ? array_keys($result['data'][0]) : 'vacio'
                ]);
                
                foreach ($result['data'] as $index => $citaBruta) {
                    Log::info("[DetalleVehiculo] Cita bruta C4C #{$index}", [
                        'uuid' => $citaBruta['uuid'] ?? 'N/A',
                        'license_plate' => $citaBruta['license_plate'] ?? 'N/A',
                        'scheduled_start_date' => $citaBruta['scheduled_start_date'] ?? 'N/A',
                        'start_time' => $citaBruta['start_time'] ?? 'N/A',
                        'appointment_status' => $citaBruta['appointment_status'] ?? 'N/A',
                        'last_change_date' => $citaBruta['last_change_date'] ?? 'N/A',
                        'creation_date' => $citaBruta['creation_date'] ?? 'N/A'
                    ]);
                }
                
                // Filtrar citas solo para este vehículo específico
                $citasVehiculo = array_filter($result['data'], function($cita) use ($placaVehiculo) {
                    // Verificar diferentes estructuras posibles para la placa
                    $placaCita = $cita['license_plate'] ?? 
                                $cita['vehicle']['plate'] ?? 
                                $cita['plate'] ?? 
                                null;
                    
                    Log::debug("[DetalleVehiculo] Comparando placas", [
                        'placa_vehiculo' => $placaVehiculo,
                        'placa_cita' => $placaCita,
                        'cita_estructura' => array_keys($cita),
                    ]);
                    
                    return $placaCita && trim($placaCita) === trim($placaVehiculo);
                });

                // LOG: Citas filtradas por placa
                Log::info("[DetalleVehiculo] ===== CITAS FILTRADAS POR PLACA =====", [
                    'total_citas_filtradas' => count($citasVehiculo),
                    'placa_vehiculo' => $placaVehiculo
                ]);

                // NUEVO: Aplicar filtros de visibilidad y remover duplicados
                $citasVehiculo = $this->aplicarFiltrosVisibilidadYDuplicados($citasVehiculo);

                Log::info("[DetalleVehiculo] Citas filtradas para vehículo {$placaVehiculo}: " . count($citasVehiculo));

                if (!empty($citasVehiculo)) {
                    $this->citasAgendadas = [];

                    foreach ($citasVehiculo as $cita) {
                        // Log para depuración de la estructura de la cita
                        Log::info('[DetalleVehiculo] Asignando fecha de cita:', [
                            'fecha_sap' => $this->datosAsesorSAP['fecha_ult_serv'] ?? 'No existe',
                            'fecha_cita' => $cita['scheduled_start_date'] ?? 'No existe',
                            'fecha_formateada' => $this->formatearFechaC4C($cita['scheduled_start_date'] ?? '')
                        ]);

                        // Mapear campos de WSCitas a la estructura de la vista (estructura real)
                        $estadoInfo = $this->obtenerInformacionEstadoCompleta($cita['status']['appointment_code'] ?? $cita['appointment_status'] ?? '1', $cita);

                        // Enriquecer con datos SAP si están disponibles
                        $citaEnriquecida = $this->enriquecerCitaConDatosSAP($cita);

                        // Obtener maintenance_type y datos de appointment desde la base de datos local
                        $localAppointmentData = $this->obtenerDatosAppointmentLocal($cita['uuid'] ?? $cita['id'] ?? '');
                        $maintenanceTypeLocal = $localAppointmentData['maintenance_type'] ?? null;

                        // Guardar los estados frontend en la base de datos
                        $this->guardarEstadosFrontendEnBD($cita['uuid'] ?? $cita['id'] ?? '', $estadoInfo);

                        $this->citasAgendadas[] = [
                            'id' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                            'numero_cita' => $cita['id'] ?? 'N/A',
                            'servicio' => $maintenanceTypeLocal ?: $this->determinarTipoServicioC4C($cita),
                            'maintenance_type' => $maintenanceTypeLocal,
                            'wildcard_selections' => $localAppointmentData['wildcard_selections'] ?? null,
                            'estado' => $estadoInfo['nombre'],
                            'fecha_cita' => $this->formatearFechaC4C($cita['scheduled_start_date'] ?? ''),
                            // CORREGIDO: Priorizar hora desde BD local, fallback a C4C
                            'hora_cita' => $localAppointmentData['appointment_time'] ?? $this->formatearHoraC4C($cita['dates']['start_time'] ?? $cita['start_time'] ?? ''),
                            'probable_entrega' => $citaEnriquecida['probable_entrega'],
                            'sede' => (\App\Models\Local::where('code', $cita['center']['id'] ?? $cita['center_id'] ?? '')->value('name') ?: ($cita['center']['id'] ?? $cita['center_id'] ?? 'No especificado')),
                            'asesor' => $citaEnriquecida['asesor'],
                            'whatsapp' => $citaEnriquecida['whatsapp'],
                            'correo' => $citaEnriquecida['correo'],
                            'comentarios' => $cita['subject'] ?? $cita['subject_name'] ?? '',
                            'status_raw' => $cita['status']['appointment_code'] ?? $cita['appointment_status'] ?? '1',
                            // Información completa del estado para la vista
                            'estado_info' => $estadoInfo,
                            // Datos adicionales de facturación SAP
                            'fecha_factura' => $citaEnriquecida['fecha_factura'],
                            'hora_factura' => $citaEnriquecida['hora_factura'],
                            'rut_pdf' => $citaEnriquecida['rut_pdf'],
                            // Campos adicionales de C4C
                            'cliente_nombre' => $cita['client_name'] ?? '',
                            'cliente_dni' => $cita['client_dni'] ?? '',
                            'vehiculo_modelo' => $cita['vehicle_model'] ?? '',
                            'vehiculo_vin' => $cita['vin'] ?? '',
                            'ubicacion' => $cita['location_name'] ?? '',
                            'prioridad' => $cita['priority_name'] ?? 'Normal',
                            // Campos adicionales del webservice
                            'centro_id' => $cita['center_id'] ?? '',
                            'solicitar_taxi' => $cita['request_taxi_name'] ?? '',
                            'telefono_fijo' => $cita['client_landline'] ?? '',
                            'direccion_cliente' => $cita['client_address'] ?? '',
                            'version_vehiculo' => $cita['vehicle_version'] ?? '',
                            'kilometraje_vehiculo' => $cita['vehicle_mileage'] ?? '',
                        ];
                    }

                    Log::info('[DetalleVehiculo] ===== CITAS TRANSFORMADAS FINALES =====', [
                        'total_citas_transformadas' => count($this->citasAgendadas)
                    ]);
                    
                    foreach ($this->citasAgendadas as $index => $citaFinal) {
                        Log::info("[DetalleVehiculo] Cita final #{$index}", [
                            'id' => $citaFinal['id'] ?? 'N/A',
                            'numero_cita' => $citaFinal['numero_cita'] ?? 'N/A',
                            'fecha_cita' => $citaFinal['fecha_cita'] ?? 'N/A',
                            'hora_cita' => $citaFinal['hora_cita'] ?? 'N/A',
                            'servicio' => $citaFinal['servicio'] ?? 'N/A',
                            'sede' => $citaFinal['sede'] ?? 'N/A',
                            'estado' => $citaFinal['estado'] ?? 'N/A'
                        ]);
                    }

                    Log::info('[DetalleVehiculo] Citas agendadas cargadas desde WSCitas: ' . count($this->citasAgendadas));
                } else {
                    Log::info("[DetalleVehiculo] No hay citas pendientes para el vehículo {$placaVehiculo}");
                    $this->citasAgendadas = [];
                }
            } else {
                Log::info("[DetalleVehiculo] No se encontraron citas pendientes en WSCitas");
                $this->citasAgendadas = [];
            }

            // Si no hay citas de WSCitas, no se cargan citas locales.
            if (empty($this->citasAgendadas)) {
                Log::info("[DetalleVehiculo] No se encontraron citas en WSCitas y el fallback local está deshabilitado.");
            }

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al cargar citas desde WSCitas: ' . $e->getMessage());
            Log::error('[DetalleVehiculo] Stack trace: ' . $e->getTraceAsString());

            // En caso de error con WSCitas, no se usará el fallback local.
            $this->citasAgendadas = [];
            Log::info("[DetalleVehiculo] Fallback a citas locales deshabilitado tras error en WSCitas.");
        }
    }

    /**
     * Aplicar filtros de visibilidad y remover duplicados de citas
     * 
     * Reglas de visibilidad según especificación del proyecto:
     * 1. Estados 1 (Generada) y 2 (Confirmada): siempre visibles
     * 2. Estados 3 (En taller): siempre visibles
     * 3. Estados 4 (Diferida), 5 (Completada) y 6 (Cancelada): filtrados (no visibles)
     * 4. Para duplicados por edición: mostrar solo la más reciente
     * 5. NUEVA REGLA: Solo una cita activa por vehículo (la más reciente)
     */
    protected function aplicarFiltrosVisibilidadYDuplicados(array $citas): array
    {
        Log::info("[DetalleVehiculo] ===== INICIANDO FILTROS DE VISIBILIDAD Y DEDUPLICACIÓN =====");
        Log::info("[DetalleVehiculo] Aplicando filtros de visibilidad, citas recibidas: " . count($citas));
        
        // Depuración: Mostrar detalle de todas las citas para revisar el estado 3
        foreach ($citas as $index => $cita) {
            $estadoCita = $cita['appointment_status'] ?? $cita['status']['appointment_code'] ?? '1';
            Log::info("[DetalleVehiculo] DEPURACIÓN: Detalle de cita #{$index}", [
                'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                'estado_simple' => $estadoCita,
                'estado_completo' => $cita['status'] ?? 'No disponible',
                'appointment_status' => $cita['appointment_status'] ?? 'No disponible',
                'status_appointment_code' => $cita['status']['appointment_code'] ?? 'No disponible',
                'estructura_cita' => array_keys($cita)
            ]);
        }
        
        $citasFiltradas = [];
        
        foreach ($citas as $cita) {
            // Mejorar obtención del estado buscando en múltiples ubicaciones posibles
            $estadoCita = $cita['appointment_status'] ?? $cita['status']['appointment_code'] ?? '1';
            $fechaCambio = $cita['last_change_date'] ?? null;
            $uuid = $cita['uuid'] ?? $cita['id'] ?? null;
            
            Log::info("[DetalleVehiculo] Evaluando cita", [
                'uuid' => $uuid,
                'estado' => $estadoCita,
                'estado_raw' => is_string($estadoCita) ? $estadoCita : json_encode($estadoCita),
                'fecha_cambio' => $fechaCambio
            ]);
            
            // Regla 1: Estados 1 (Generada), 2 (Confirmada) y 3 (En proceso) siempre visibles
            // Asegurar que las comparaciones sean con strings para evitar problemas de tipo
            if (in_array((string)$estadoCita, ['1', '2', '3'])) {
                Log::info("[DetalleVehiculo] Cita incluida - Estado {$estadoCita} (Generada/Confirmada/En proceso)");
                $citasFiltradas[] = $cita;
                continue;
            }
            
            // Regla 2: Estados 4 (Diferida), 5 (Completada) y 6 (Cancelada) no visibles
            if (in_array((string)$estadoCita, ['4', '5', '6'])) {
                Log::info("[DetalleVehiculo] Cita filtrada - Estado {$estadoCita} (Diferida/Completada/Cancelada)");
                continue;
            }
            
            // Para cualquier otro estado no definido, incluir por seguridad
            Log::info("[DetalleVehiculo] Cita incluida - Estado no definido: {$estadoCita}");
            $citasFiltradas[] = $cita;
        }
        
        // NUEVA REGLA 5: Solo una cita activa por vehículo
        $citaUnica = $this->seleccionarSoloCitaMasReciente($citasFiltradas);
        
        Log::info("[DetalleVehiculo] Filtros aplicados", [
            'citas_originales' => count($citas),
            'citas_filtradas' => count($citasFiltradas),
            'citas_finales' => count($citaUnica)
        ]);
        
        // LOG FINAL: Mostrar resultado de deduplicación
        Log::info("[DetalleVehiculo] ===== RESULTADO FINAL DE DEDUPLICACIÓN =====");
        foreach ($citaUnica as $index => $citaFinal) {
            Log::info("[DetalleVehiculo] Cita final resultado #{$index}", [
                'uuid' => $citaFinal['uuid'] ?? $citaFinal['id'] ?? 'N/A',
                'scheduled_start_date' => $citaFinal['scheduled_start_date'] ?? 'N/A',
                'appointment_status' => $citaFinal['appointment_status'] ?? 'N/A',
                'status_appointment_code' => $citaFinal['status']['appointment_code'] ?? 'N/A',
                'last_change_date' => $citaFinal['last_change_date'] ?? 'N/A'
            ]);
        }
        
        return $citaUnica;
    }

    /**
     * Evaluar si una cita con estado "Trabajo concluido" en el frontend debe expirar
     * 
     * Lógica: La cita debe ser visible hasta el día siguiente a las 11:59pm
     * después de que se marcó como "Trabajo concluido" en el frontend
     * 
     * NOTA: Esta lógica se aplica solo al estado frontend "Trabajo concluido"
     * y no está relacionada con el estado C4C 5 (Completada) ya que este
     * último ahora se filtra (no se muestra) junto con el estado 6.
     * 
     * @param array $cita Datos de la cita
     * @param \Carbon\Carbon $ahora Fecha/hora actual
     * @return bool true si debe expirar (ocultar), false si sigue siendo visible
     */
    protected function evaluarExpiracionTrabajoCompletado(array $cita, \Carbon\Carbon $ahora): bool
    {
        try {
            // Verificar si el estado en el frontend es "Trabajo concluido"
            // Esto se determina por los datos de SAP, no por el estado de C4C
            $estadoInfo = $this->obtenerInformacionEstadoCompleta($cita['appointment_status'] ?? '1', $cita);
            $esTrabajoConcluido = ($estadoInfo['etapas']['trabajo_concluido']['completado'] ?? false) && 
                                 ($estadoInfo['etapas']['trabajo_concluido']['activo'] ?? false);
            
            // Si no está en estado "Trabajo concluido", no aplicar expiración
            if (!$esTrabajoConcluido) {
                return false;
            }
            
            // Obtener la fecha de cambio (cuando se marcó como completada)
            $fechaCambio = $cita['last_change_date'] ?? null;
            
            // Si no hay fecha de cambio, asumir que es válida (no expirar)
            if (empty($fechaCambio)) {
                Log::debug("[DetalleVehiculo] Cita en estado Trabajo Concluido sin fecha_cambio - mantener visible", [
                    'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A'
                ]);
                return false;
            }
            
            // Parsear la fecha de cambio
            $fechaCambioCarbon = \Carbon\Carbon::parse($fechaCambio);
            
            // Calcular el límite de expiración: día siguiente a las 11:59:59 PM
            $limiteExpiracion = $fechaCambioCarbon->copy()
                ->addDay() // Día siguiente
                ->endOfDay(); // 23:59:59
            
            // Verificar si ya expiró
            $haExpirado = $ahora->isAfter($limiteExpiracion);
            
            Log::info("[DetalleVehiculo] Evaluación expiración cita estado Trabajo Concluido", [
                'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                'fecha_cambio' => $fechaCambio,
                'fecha_cambio_parsed' => $fechaCambioCarbon->toDateTimeString(),
                'limite_expiracion' => $limiteExpiracion->toDateTimeString(),
                'ahora' => $ahora->toDateTimeString(),
                'ha_expirado' => $haExpirado ? 'SÍ' : 'NO',
                'tiempo_restante' => $haExpirado ? '0' : $ahora->diffForHumans($limiteExpiracion, true)
            ]);
            
            return $haExpirado;
            
        } catch (\Exception $e) {
            // En caso de error al parsear fechas, mantener la cita visible (no expirar)
            Log::error("[DetalleVehiculo] Error al evaluar expiración de cita estado Trabajo Concluido", [
                'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                'error' => $e->getMessage(),
                'fecha_cambio_raw' => $fechaCambio ?? 'NULL'
            ]);
            return false;
        }
    }
    
    /**
     * Remover duplicados manteniendo la cita más reciente por fecha de cambio
     * Esto maneja el caso donde una edición crea una nueva cita pero mantiene la anterior
     */
    protected function removerDuplicadosPorFechaCambio(array $citas): array
    {
        if (count($citas) <= 1) {
            return $citas;
        }
        
        Log::info("[DetalleVehiculo] Iniciando deduplicación de citas", [
            'total_citas' => count($citas)
        ]);
        
        // Debug: mostrar todas las citas recibidas
        foreach ($citas as $index => $cita) {
            Log::info("[DetalleVehiculo] Cita {$index}", [
                'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                'fecha_agendada' => $cita['scheduled_start_date'] ?? 'N/A',
                'hora_inicio' => $cita['start_time'] ?? 'N/A',
                'centro_id' => $cita['center_id'] ?? 'N/A',
                'fecha_cambio' => $cita['last_change_date'] ?? 'N/A',
                'estado' => $cita['appointment_status'] ?? 'N/A'
            ]);
        }
        
        // Enfoque 1: Agrupar por UUID base (las citas editadas pueden compartir parte del UUID)
        $citasPorUUID = $this->agruparPorUUIDBase($citas);
        
        // Enfoque 2: Si no hay duplicados por UUID, agrupar por similitud de fecha/hora/centro
        if (count($citasPorUUID) === count($citas)) {
            Log::info("[DetalleVehiculo] No se encontraron duplicados por UUID, verificando similitud fecha/hora");
            $citasPorUUID = $this->agruparPorSimilitud($citas);
        }
        
        $citasFinales = [];
        
        foreach ($citasPorUUID as $claveGrupo => $grupo) {
            if (count($grupo) === 1) {
                // Solo una cita en este grupo, mantenerla
                $citasFinales[] = $grupo[0];
            } else {
                // Múltiples citas en el grupo, quedarse con la más reciente
                Log::info("[DetalleVehiculo] Detectadas citas duplicadas en grupo '{$claveGrupo}'", [
                    'cantidad_duplicadas' => count($grupo)
                ]);
                
                $citaMasReciente = $this->seleccionarCitaMasReciente($grupo);
                
                Log::info("[DetalleVehiculo] Cita más reciente seleccionada", [
                    'uuid_seleccionado' => $citaMasReciente['uuid'] ?? $citaMasReciente['id'] ?? 'N/A',
                    'fecha_cambio' => $citaMasReciente['last_change_date'] ?? 'N/A',
                    'descartadas' => count($grupo) - 1
                ]);
                
                $citasFinales[] = $citaMasReciente;
            }
        }
        
        Log::info("[DetalleVehiculo] Deduplicación completada", [
            'citas_originales' => count($citas),
            'citas_finales' => count($citasFinales),
            'duplicados_removidos' => count($citas) - count($citasFinales)
        ]);
        
        return $citasFinales;
    }
    
    /**
     * Agrupar citas por base del UUID (para detectar ediciones)
     */
    protected function agruparPorUUIDBase(array $citas): array
    {
        $grupos = [];
        
        foreach ($citas as $cita) {
            $uuid = $cita['uuid'] ?? $cita['id'] ?? '';
            
            if (empty($uuid)) {
                // Si no tiene UUID, crear grupo único
                $claveGrupo = 'sin_uuid_' . uniqid();
                $grupos[$claveGrupo] = [$cita];
                continue;
            }
            
            // Extraer base del UUID (primeras 3 secciones)
            $partesUUID = explode('-', $uuid);
            if (count($partesUUID) >= 3) {
                $baseUUID = implode('-', array_slice($partesUUID, 0, 3));
            } else {
                $baseUUID = $uuid;
            }
            
            if (!isset($grupos[$baseUUID])) {
                $grupos[$baseUUID] = [];
            }
            $grupos[$baseUUID][] = $cita;
        }
        
        return $grupos;
    }
    
    /**
     * Agrupar citas por similitud de fecha/hora/centro (fallback)
     */
    protected function agruparPorSimilitud(array $citas): array
    {
        $grupos = [];
        
        foreach ($citas as $cita) {
            $fechaAgendada = $cita['scheduled_start_date'] ?? '';
            $centroId = $cita['center_id'] ?? '';
            
            // Crear clave de similitud más flexible
            $claveSimilitud = $fechaAgendada . '_' . $centroId;
            
            if (!isset($grupos[$claveSimilitud])) {
                $grupos[$claveSimilitud] = [];
            }
            $grupos[$claveSimilitud][] = $cita;
        }
        
        return $grupos;
    }
    
    /**
     * Seleccionar la cita más reciente de un grupo
     */
    protected function seleccionarCitaMasReciente(array $grupo): array
    {
        $citaMasReciente = $grupo[0];
        
        foreach ($grupo as $cita) {
            $fechaCambioActual = $cita['last_change_date'] ?? $cita['creation_date'] ?? '';
            $fechaCambioMasReciente = $citaMasReciente['last_change_date'] ?? $citaMasReciente['creation_date'] ?? '';
            
            // Si las fechas están vacías, usar el estado como criterio secundario
            if (empty($fechaCambioActual) && empty($fechaCambioMasReciente)) {
                $estadoActual = $cita['appointment_status'] ?? '1';
                $estadoMasReciente = $citaMasReciente['appointment_status'] ?? '1';
                
                // Preferir estados más avanzados (2 > 1)
                if ($estadoActual > $estadoMasReciente) {
                    $citaMasReciente = $cita;
                }
            } elseif ($fechaCambioActual > $fechaCambioMasReciente) {
                $citaMasReciente = $cita;
            }
        }
        
        return $citaMasReciente;
    }
    
    /**
     * NUEVA REGLA: Seleccionar solo la cita más reciente para este vehículo
     * Esto resuelve el problema de múltiples citas activas después de ediciones
     */
    protected function seleccionarSoloCitaMasReciente(array $citas): array
    {
        if (count($citas) <= 1) {
            return $citas;
        }
        
        Log::info("[DetalleVehiculo] Múltiples citas encontradas, seleccionando solo la más reciente", [
            'total_citas' => count($citas)
        ]);
        
        // Debug: mostrar todas las citas antes de la selección
        foreach ($citas as $index => $cita) {
            Log::info("[DetalleVehiculo] Cita {$index} para selección", [
                'uuid' => $cita['uuid'] ?? $cita['id'] ?? 'N/A',
                'fecha_agendada' => $cita['scheduled_start_date'] ?? 'N/A',
                'hora_inicio' => $cita['start_time'] ?? 'N/A',
                'fecha_cambio' => $cita['last_change_date'] ?? 'N/A',
                'fecha_creacion' => $cita['creation_date'] ?? 'N/A',
                'estado' => $cita['appointment_status'] ?? 'N/A'
            ]);
        }
        
        $citaMasReciente = $citas[0];
        
        foreach ($citas as $cita) {
            // Criterio 1: Fecha de cambio más reciente
            $fechaCambioActual = $cita['last_change_date'] ?? '';
            $fechaCambioMasReciente = $citaMasReciente['last_change_date'] ?? '';
            
            if (!empty($fechaCambioActual) && !empty($fechaCambioMasReciente)) {
                if ($fechaCambioActual > $fechaCambioMasReciente) {
                    $citaMasReciente = $cita;
                    continue;
                }
            }
            
            // Criterio 2: Si no hay fechas de cambio, usar fecha de creación
            if (empty($fechaCambioActual) && empty($fechaCambioMasReciente)) {
                $fechaCreacionActual = $cita['creation_date'] ?? '';
                $fechaCreacionMasReciente = $citaMasReciente['creation_date'] ?? '';
                
                if (!empty($fechaCreacionActual) && !empty($fechaCreacionMasReciente)) {
                    if ($fechaCreacionActual > $fechaCreacionMasReciente) {
                        $citaMasReciente = $cita;
                        continue;
                    }
                }
            }
            
            // Criterio 3: Si no hay fechas, usar fecha agendada más reciente
            if (empty($fechaCambioActual) && empty($fechaCambioMasReciente)) {
                $fechaAgendadaActual = $cita['scheduled_start_date'] ?? '';
                $fechaAgendadaMasReciente = $citaMasReciente['scheduled_start_date'] ?? '';
                
                if (!empty($fechaAgendadaActual) && !empty($fechaAgendadaMasReciente)) {
                    if ($fechaAgendadaActual > $fechaAgendadaMasReciente) {
                        $citaMasReciente = $cita;
                        continue;
                    }
                }
            }
        }
        
        Log::info("[DetalleVehiculo] Cita seleccionada como la más reciente", [
            'uuid_seleccionado' => $citaMasReciente['uuid'] ?? $citaMasReciente['id'] ?? 'N/A',
            'fecha_agendada' => $citaMasReciente['scheduled_start_date'] ?? 'N/A',
            'fecha_cambio' => $citaMasReciente['last_change_date'] ?? 'N/A',
            'citas_descartadas' => count($citas) - 1
        ]);
        
        return [$citaMasReciente];
    }

    /**
     * Cargar citas pendientes desde la base de datos local como fallback
     */
    protected function cargarCitasLocalesPendientes(int $vehiculoId): void
    {
        try {
            Log::info("[DetalleVehiculo] Cargando citas locales para vehículo ID: {$vehiculoId}");

            // Obtener citas pendientes/confirmadas desde la BD local
            $citasLocales = Appointment::with(['premise'])
                ->where('vehicle_id', $vehiculoId)
                ->whereIn('status', ['pending', 'confirmed', 'generated'])
                ->where('appointment_date', '>=', now()->format('Y-m-d'))
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get();

            Log::info("[DetalleVehiculo] Citas locales encontradas: " . $citasLocales->count());

            if ($citasLocales->isNotEmpty()) {
                Log::info("[DetalleVehiculo] ===== PROCESANDO CITAS LOCALES ANTES DE DEDUPLICACIÓN =====", [
                    'total_citas_locales' => $citasLocales->count()
                ]);
                
                // Crear array temporal para aplicar deduplicación
                $citasParaDeduplicar = [];
                
                foreach ($citasLocales as $index => $cita) {
                    Log::info("[DetalleVehiculo] Cita local #{$index} antes de deduplicación", [
                        'id' => $cita->id,
                        'appointment_number' => $cita->appointment_number,
                        'appointment_date' => $cita->appointment_date ? $cita->appointment_date->format('Y-m-d') : 'N/A',
                        'appointment_time' => $cita->appointment_time ? $cita->appointment_time->format('H:i') : 'N/A',
                        'maintenance_type' => $cita->maintenance_type,
                        'status' => $cita->status,
                        'created_at' => $cita->created_at ? $cita->created_at->format('Y-m-d H:i:s') : 'N/A',
                        'updated_at' => $cita->updated_at ? $cita->updated_at->format('Y-m-d H:i:s') : 'N/A'
                    ]);
                    
                    // Convertir a formato similar al de C4C para usar la misma lógica de deduplicación
                    $citasParaDeduplicar[] = [
                        'uuid' => $cita->c4c_uuid ?? 'local-' . $cita->id,
                        'id' => $cita->id,
                        'scheduled_start_date' => $cita->appointment_date ? $cita->appointment_date->format('Y-m-d') : '',
                        'start_time' => $cita->appointment_time ? $cita->appointment_time->format('H:i:s') : '',
                        'appointment_status' => $this->mapearEstadoLocalAC4C($cita->status),
                        'last_change_date' => $cita->updated_at ? $cita->updated_at->format('Y-m-d H:i:s') : '',
                        'creation_date' => $cita->created_at ? $cita->created_at->format('Y-m-d H:i:s') : '',
                        'center_id' => $cita->premise_id ?? '',
                        // Guardar el objeto original para usar después
                        '_original_cita' => $cita
                    ];
                }
                
                // APLICAR DEDUPLICACIÓN A CITAS LOCALES
                Log::info("[DetalleVehiculo] ===== APLICANDO DEDUPLICACIÓN A CITAS LOCALES =====");
                $citasDeduplicadas = $this->seleccionarSoloCitaMasReciente($citasParaDeduplicar);
                
                Log::info("[DetalleVehiculo] ===== RESULTADO DEDUPLICACIÓN CITAS LOCALES =====", [
                    'citas_originales' => count($citasParaDeduplicar),
                    'citas_finales' => count($citasDeduplicadas)
                ]);
                
                $this->citasAgendadas = [];

                foreach ($citasDeduplicadas as $citaDedup) {
                    // Recuperar el objeto original de la cita
                    $cita = $citaDedup['_original_cita'];
                    
                    Log::info("[DetalleVehiculo] Procesando cita local deduplicada", [
                        'id' => $cita->id,
                        'appointment_number' => $cita->appointment_number,
                        'fecha' => $cita->appointment_date ? $cita->appointment_date->format('d/m/Y') : '-',
                        'hora' => $cita->appointment_time ? $cita->appointment_time->format('H:i') : '-'
                    ]);
                    $estadoInfo = $this->obtenerInformacionEstadoCompletaLocal($cita->status, $cita);

                    // Crear datos base de la cita local
                    $citaLocal = [
                        'customer_phone' => $cita->customer_phone,
                        'customer_email' => $cita->customer_email,
                        'appointment_date' => $cita->appointment_date,
                        'appointment_time' => $cita->appointment_time,
                    ];

                    // Enriquecer con datos SAP si están disponibles
                    $citaEnriquecida = $this->enriquecerCitaConDatosSAP($citaLocal);

                    // Guardar los estados frontend en la base de datos
                    $this->guardarEstadosFrontendEnBD($cita->c4c_uuid ?? 'local-' . $cita->id, $estadoInfo);

                    $this->citasAgendadas[] = [
                        'id' => $cita->c4c_uuid ?? 'local-' . $cita->id,
                        'numero_cita' => $cita->appointment_number ?? 'CITA-' . $cita->id,
                        'servicio' => $this->determinarTipoServicio($cita->maintenance_type, $cita->service_mode),
                        'maintenance_type' => $cita->maintenance_type,
                        'estado' => $estadoInfo['nombre'],
                        'fecha_cita' => $cita->appointment_date ? $cita->appointment_date->format('d/m/Y') : '-',
                        'hora_cita' => $cita->appointment_time ? $cita->appointment_time->format('H:i') : '-',
                        'probable_entrega' => $citaEnriquecida['probable_entrega'],
                        'sede' => $cita->premise->name ?? 'Por confirmar',
                        'asesor' => $citaEnriquecida['asesor'],
                        'whatsapp' => $citaEnriquecida['whatsapp'],
                        'correo' => $citaEnriquecida['correo'],
                        'comentarios' => $cita->comments ?? '',
                        'status_raw' => $this->mapearEstadoLocalAC4C($cita->status),
                        // Información completa del estado para la vista
                        'estado_info' => $estadoInfo,
                        // Campos adicionales de la BD local
                        'cliente_nombre' => $cita->customer_name . ' ' . $cita->customer_last_name,
                        'cliente_dni' => $cita->customer_ruc ?? '-',
                        'vehiculo_modelo' => $this->vehiculo['modelo'] ?? '-',
                        'vehiculo_vin' => '-',
                        'ubicacion' => $cita->premise->name ?? 'Por confirmar',
                        'prioridad' => 'Normal',
                        // Campos adicionales
                        'centro_id' => $cita->premise_id ?? '',
                        'solicitar_taxi' => '-',
                        'telefono_fijo' => '-',
                        'direccion_cliente' => '-',
                        'version_vehiculo' => '-',
                        'kilometraje_vehiculo' => '-',
                        // Indicador de que es cita local
                        'fuente' => 'local',
                        'sincronizada' => $cita->is_synced ? 'Sí' : 'Pendiente',
                    ];
                }

                Log::info("[DetalleVehiculo] ===== CITAS LOCALES FINALES TRANSFORMADAS =====", [
                    'total_citas_finales' => count($this->citasAgendadas)
                ]);
                
                foreach ($this->citasAgendadas as $index => $citaFinal) {
                    Log::info("[DetalleVehiculo] Cita local final #{$index}", [
                        'id' => $citaFinal['id'] ?? 'N/A',
                        'numero_cita' => $citaFinal['numero_cita'] ?? 'N/A',
                        'fecha_cita' => $citaFinal['fecha_cita'] ?? 'N/A',
                        'hora_cita' => $citaFinal['hora_cita'] ?? 'N/A',
                        'servicio' => $citaFinal['servicio'] ?? 'N/A',
                        'sede' => $citaFinal['sede'] ?? 'N/A'
                    ]);
                }

                Log::info('[DetalleVehiculo] Citas locales cargadas exitosamente: ' . count($this->citasAgendadas));
            } else {
                Log::info("[DetalleVehiculo] No hay citas locales pendientes para el vehículo ID: {$vehiculoId}");
                $this->citasAgendadas = [];
            }

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al cargar citas locales: ' . $e->getMessage());
            Log::error('[DetalleVehiculo] Stack trace: ' . $e->getTraceAsString());
            
            // En caso de error, asegurar que el array esté inicializado
            $this->citasAgendadas = [];
        }
    }

    /**
     * Calcular probable entrega para cita local
     */
    protected function calcularProbableEntregaLocal(Appointment $cita): string
    {
        if ($cita->appointment_end_time) {
            return $cita->appointment_date->format('d/m/Y') . ' ' . $cita->appointment_end_time->format('H:i');
        }

        // Estimar 4 horas después del inicio
        $fechaInicio = $cita->appointment_date;
        $horaInicio = $cita->appointment_time;

        if ($fechaInicio && $horaInicio) {
            $fechaHoraInicio = $fechaInicio->setTimeFrom($horaInicio);
            $fechaHoraFin = $fechaHoraInicio->copy()->addHours(4);
            return $fechaHoraFin->format('d/m/Y H:i');
        }

        return '-';
    }

    /**
     * Mapear estado local a código C4C
     */
    protected function mapearEstadoLocalAC4C(string $statusLocal): string
    {
        return match ($statusLocal) {
            'pending' => '1',      // Generada
            'confirmed' => '2',    // Confirmada
            'generated' => '1',    // Generada
            'in_progress' => '3',  // En proceso
            'completed' => '5',    // Completada
            'cancelled' => '6',    // Cancelada
            default => '1',
        };
    }

    /**
     * Save frontend states to the database for a specific appointment
     */
    protected function guardarEstadosFrontendEnBD(string $c4cUuid, array $estadoInfo): void
    {
        try {
            $appointment = Appointment::where('c4c_uuid', $c4cUuid)->first();
            
            if (!$appointment) {
                Log::warning("[DetalleVehiculo] No se encontró la cita para guardar estados frontend: {$c4cUuid}");
                return;
            }

            $currentStates = $appointment->frontend_states ?? [];
            $now = now()->format('Y-m-d H:i:s');
            
            // Procesar cita_confirmada
            $citaConfirmadaActivo = $estadoInfo['etapas']['cita_confirmada']['activo'] ?? false;
            $citaConfirmadaCompletado = $estadoInfo['etapas']['cita_confirmada']['completado'] ?? false;
            
            $currentCitaConfirmada = $currentStates['cita_confirmada'] ?? [];
            $previousActivo = $currentCitaConfirmada['activo'] ?? false;
            $previousCompletado = $currentCitaConfirmada['completado'] ?? false;
            
            // Actualizar timestamp si hay cambio de estado o es la primera vez
            if (!isset($currentCitaConfirmada['timestamp']) || 
                $citaConfirmadaActivo != $previousActivo || 
                $citaConfirmadaCompletado != $previousCompletado) {
                
                if ($citaConfirmadaActivo || $citaConfirmadaCompletado) {
                    $currentCitaConfirmada['timestamp'] = $now;
                    Log::info("[DetalleVehiculo] Cita confirmada actualizada con timestamp: {$now}", [
                        'cambio_activo' => "{$previousActivo} -> {$citaConfirmadaActivo}",
                        'cambio_completado' => "{$previousCompletado} -> {$citaConfirmadaCompletado}"
                    ]);
                }
            }
            
            $currentCitaConfirmada['activo'] = $citaConfirmadaActivo;
            $currentCitaConfirmada['completado'] = $citaConfirmadaCompletado;
            $currentStates['cita_confirmada'] = $currentCitaConfirmada;
            
            // Procesar en_trabajo
            $enTrabajoActivo = $estadoInfo['etapas']['en_trabajo']['activo'] ?? false;
            $enTrabajoCompletado = $estadoInfo['etapas']['en_trabajo']['completado'] ?? false;
            
            $currentEnTrabajo = $currentStates['en_trabajo'] ?? [];
            $previousEnTrabajoActivo = $currentEnTrabajo['activo'] ?? false;
            $previousEnTrabajoCompletado = $currentEnTrabajo['completado'] ?? false;
            
            // Actualizar timestamp si hay cambio de estado o es la primera vez
            if (!isset($currentEnTrabajo['timestamp']) || 
                $enTrabajoActivo != $previousEnTrabajoActivo || 
                $enTrabajoCompletado != $previousEnTrabajoCompletado) {
                
                if ($enTrabajoActivo || $enTrabajoCompletado) {
                    $currentEnTrabajo['timestamp'] = $now;
                    Log::info("[DetalleVehiculo] En trabajo actualizado con timestamp: {$now}", [
                        'cambio_activo' => "{$previousEnTrabajoActivo} -> {$enTrabajoActivo}",
                        'cambio_completado' => "{$previousEnTrabajoCompletado} -> {$enTrabajoCompletado}"
                    ]);
                }
            }
            
            $currentEnTrabajo['activo'] = $enTrabajoActivo;
            $currentEnTrabajo['completado'] = $enTrabajoCompletado;
            $currentStates['en_trabajo'] = $currentEnTrabajo;
            
            // Procesar trabajo_concluido
            $trabajoConcluidoActivo = $estadoInfo['etapas']['trabajo_concluido']['activo'] ?? false;
            $trabajoConcluidoCompletado = $estadoInfo['etapas']['trabajo_concluido']['completado'] ?? false;
            
            $currentTrabajoConcluido = $currentStates['trabajo_concluido'] ?? [];
            $previousTrabajoActivo = $currentTrabajoConcluido['activo'] ?? false;
            $previousTrabajoCompletado = $currentTrabajoConcluido['completado'] ?? false;
            
            // Actualizar timestamp si hay cambio de estado o es la primera vez
            if (!isset($currentTrabajoConcluido['timestamp']) || 
                $trabajoConcluidoActivo != $previousTrabajoActivo || 
                $trabajoConcluidoCompletado != $previousTrabajoCompletado) {
                
                if ($trabajoConcluidoActivo || $trabajoConcluidoCompletado) {
                    $currentTrabajoConcluido['timestamp'] = $now;
                    Log::info("[DetalleVehiculo] Trabajo concluido actualizado con timestamp: {$now}", [
                        'cambio_activo' => "{$previousTrabajoActivo} -> {$trabajoConcluidoActivo}",
                        'cambio_completado' => "{$previousTrabajoCompletado} -> {$trabajoConcluidoCompletado}"
                    ]);
                }
            }
            
            $currentTrabajoConcluido['activo'] = $trabajoConcluidoActivo;
            $currentTrabajoConcluido['completado'] = $trabajoConcluidoCompletado;
            $currentStates['trabajo_concluido'] = $currentTrabajoConcluido;
            
            $appointment->frontend_states = $currentStates;
            $appointment->save();
            
            Log::info("[DetalleVehiculo] Estados frontend guardados en BD para cita: {$c4cUuid}", [
                'frontend_states' => $currentStates
            ]);
            
        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al guardar estados frontend en BD: " . $e->getMessage());
        }
    }

    /**
     * Retrieve frontend states from the database for a specific appointment
     */
    protected function obtenerEstadosFrontendDeBD(string $c4cUuid): ?array
    {
        try {
            // Find the appointment by c4c_uuid and retrieve the frontend_states
            $appointment = Appointment::where('c4c_uuid', $c4cUuid)->first();
            
            if ($appointment && !empty($appointment->frontend_states)) {
                Log::info("[DetalleVehiculo] Estados frontend recuperados de BD para cita: {$c4cUuid}", [
                    'frontend_states' => $appointment->frontend_states
                ]);
                return $appointment->frontend_states;
            } else {
                Log::info("[DetalleVehiculo] No se encontraron estados frontend en BD para cita: {$c4cUuid}");
                return null;
            }
        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al obtener estados frontend de BD: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener información de estado para citas locales
     */
    protected function obtenerInformacionEstadoCompletaLocal(string $statusLocal, $appointment = null): array
    {
        $estadoC4C = $this->mapearEstadoLocalAC4C($statusLocal);
        
        // Preparar datos de la cita si están disponibles
        $appointmentData = null;
        if ($appointment) {
            $appointmentData = [
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'scheduled_start_date' => $appointment->appointment_date ? $appointment->appointment_date->format('Y-m-d') : null,
                'uuid' => $appointment->c4c_uuid ?? 'local-' . $appointment->id,
                'id' => 'local-' . $appointment->id,
            ];
        }
        
        return $this->obtenerInformacionEstadoCompleta($estadoC4C, $appointmentData);
    }

    /**
     * Obtener datos de appointment desde la base de datos local usando el UUID de C4C
     * CORREGIDO: Ahora obtiene maintenance_type, appointment_time y wildcard_selections para evitar inconsistencias
     */
    protected function obtenerDatosAppointmentLocal(string $uuid): array
    {
        try {
            if (empty($uuid)) {
                return [];
            }

            Log::info("[DetalleVehiculo] Buscando datos de appointment para UUID: {$uuid}");

            // Buscar la cita en la base de datos local usando el c4c_uuid
            $appointment = Appointment::where('c4c_uuid', $uuid)->first();

            if ($appointment) {
                $appointmentTime = null;
                if ($appointment->appointment_time) {
                    // Formatear la hora directamente desde la BD (ya está en hora local de Perú)
                    $appointmentTime = $appointment->appointment_time instanceof \Carbon\Carbon 
                        ? $appointment->appointment_time->format('H:i')
                        : (is_string($appointment->appointment_time) ? substr($appointment->appointment_time, 0, 5) : null);
                }
                
                // ✅ INCLUIR wildcard_selections
                $wildcardSelections = null;
                if ($appointment->wildcard_selections) {
                    $wildcardSelections = json_decode($appointment->wildcard_selections, true);
                }
                
                $result = [
                    'maintenance_type' => $appointment->maintenance_type,
                    'appointment_time' => $appointmentTime,
                    'wildcard_selections' => $wildcardSelections
                ];
                
                Log::info("[DetalleVehiculo] Datos de appointment encontrados", [
                    'uuid' => $uuid,
                    'maintenance_type' => $result['maintenance_type'] ?? 'NULL',
                    'appointment_time' => $result['appointment_time'] ?? 'NULL',
                    'has_wildcard_selections' => !empty($wildcardSelections),
                    'appointment_time_raw' => $appointment->appointment_time
                ]);
                
                return $result;
            }

            Log::info("[DetalleVehiculo] No se encontraron datos de appointment para UUID: {$uuid}");
            return [];

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al obtener datos de appointment: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener maintenance_type desde la base de datos local usando el UUID de C4C
     * DEPRECATED: Usar obtenerDatosAppointmentLocal() en su lugar
     */
    protected function obtenerMaintenanceTypeLocal(string $uuid): ?string
    {
        $data = $this->obtenerDatosAppointmentLocal($uuid);
        return $data['maintenance_type'] ?? null;
    }

    protected function inicializarHistorialServicios(): void
    {
        $servicios = [];

        // Si tenemos un vehículo cargado, buscamos su historial de citas completadas
        if (isset($this->vehiculo['id'])) {
            Log::info("[DetalleVehiculo] Buscando historial de citas para el vehículo ID: {$this->vehiculo['id']}");

            try {
                $citasCompletadas = Appointment::with(['premise'])
                    ->where('vehicle_id', $this->vehiculo['id'])
                    ->where('status', 'completed')
                    ->orderBy('appointment_date', 'desc')
                    ->get();

                Log::info("[DetalleVehiculo] Se encontraron {$citasCompletadas->count()} citas completadas");

                foreach ($citasCompletadas as $cita) {
                    $tipoServicio = $this->determinarTipoServicio($cita->maintenance_type, $cita->service_mode);

                    $servicios[] = [
                        'servicio' => $tipoServicio,
                        'maintenance_type' => $cita->maintenance_type,
                        'fecha' => $cita->appointment_date ? $cita->appointment_date->format('d/m/Y') : 'No disponible',
                        'sede' => $cita->premise->name ?? 'No especificado',
                        'asesor' => 'Asesor asignado',
                        'tipo_pago' => $this->determinarTipoPago($cita),
                    ];
                }
            } catch (\Exception $e) {
                Log::error('[DetalleVehiculo] Error al cargar historial de citas: '.$e->getMessage());
            }
        } else {
            Log::warning('[DetalleVehiculo] No se puede cargar el historial de citas porque no hay un vehículo cargado');
        }

        // Si no hay servicios completados, agregamos un ejemplo
        if (empty($servicios)) {
            Log::info('[DetalleVehiculo] No hay servicios completados, agregando ejemplo');

            // Si tenemos un vehículo cargado, personalizamos el ejemplo
            if (isset($this->vehiculo['modelo'])) {
                $servicios[] = [
                    'servicio' => 'Mantenimiento 10,000 Km',
                    'fecha' => date('d/m/Y', strtotime('-3 months')),
                    'sede' => 'Mitsui La Molina',
                    'asesor' => 'Luis Gonzales',
                    'tipo_pago' => 'Contado',
                ];
            } else {
                $servicios[] = [
                    'servicio' => 'Mantenimiento 15,000 Km',
                    'fecha' => '30/10/2023',
                    'sede' => 'La Molina',
                    'asesor' => 'Luis Gonzales',
                    'tipo_pago' => 'Contado',
                ];
            }
        }

        $this->historialServicios = collect($servicios);
        Log::info('[DetalleVehiculo] Historial de servicios inicializado con '.count($servicios).' servicios');
    }

    public function getHistorialPaginadoProperty(): LengthAwarePaginator
    {
        $page = request()->query('page', $this->currentPage);

        return new LengthAwarePaginator(
            $this->historialServicios->forPage($page, $this->perPage),
            $this->historialServicios->count(),
            $this->perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }

    // Método para volver a la página de vehículos
    public function volver(): void
    {
        $this->redirect(Vehiculos::getUrl());
    }

    // Método para forzar recarga de datos SAP (útil para debugging)
    public function recargarDatosSAP(): void
    {
        if (!isset($this->vehiculo['placa'])) {
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('No hay vehículo cargado para recargar datos SAP.')
                ->danger()
                ->send();
            return;
        }

        $placa = trim(str_replace(' ', '', $this->vehiculo['placa']));
        
        Log::info("[DetalleVehiculo] 🔄 RECARGA MANUAL DE DATOS SAP solicitada para placa: {$placa}");
        
        if (config('vehiculos_webservice.enabled', true)) {
            $this->cargarDatosVehiculoDesdeSAP($placa);
            
            // Recargar citas con los nuevos datos SAP
            $vehiculo = Vehicle::where('license_plate', $placa)->first();
            if ($vehiculo) {
                $this->cargarCitasAgendadas($vehiculo->id);
            }
            
            \Filament\Notifications\Notification::make()
                ->title('Datos SAP Recargados')
                ->body('Los datos del vehículo y estados de citas han sido actualizados desde SAP.')
                ->success()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('SAP Deshabilitado')
                ->body('El webservice SAP está deshabilitado en la configuración.')
                ->warning()
                ->send();
        }
    }

    /**
     * Método para ir a agendar cita
     */
    public function agendarCita(): void
    {
        // Verificar si tenemos un vehículo cargado
        if (! isset($this->vehiculo['placa'])) {
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('No se ha seleccionado un vehículo válido.')
                ->danger()
                ->send();

            return;
        }

        // Asegurarse de que la placa no tenga espacios adicionales
        $placa = $this->vehiculo['placa'] ?? '';
        $placa = trim(str_replace(' ', '', $placa));

        // Verificar si el vehículo existe en la base de datos
        $vehiculo = Vehicle::where('license_plate', $placa)->first();

        if (! $vehiculo) {
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('El vehículo no se encuentra registrado en la base de datos.')
                ->danger()
                ->send();

            return;
        }

        // Registrar la placa original y la placa limpia
        Log::info('[DetalleVehiculo] Datos del vehículo para agendar cita:', [
            'placa_original' => $this->vehiculo['placa'] ?? 'No disponible',
            'placa_limpia' => $placa,
            'modelo' => $this->vehiculo['modelo'] ?? 'No disponible',
            'vehicle_id' => $vehiculo->id,
        ]);

        // Redirigir a la página de agendar cita con la placa como parámetro
        $this->redirect(AgendarCita::getUrl(['vehiculoId' => $placa]));
    }

    /**
     * Editar una cita existente - Redirige a agendar cita en modo edición
     */
    public function editarCita(array $citaData): void
    {
        Log::info('🔧 [DetalleVehiculo::editarCita] ========== INICIO EDICIÓN CITA ==========');
        Log::info('[DetalleVehiculo::editarCita] Iniciando edición de cita', [
            'cita_id' => $citaData['id'] ?? 'N/A',
            'vehiculo_id' => $this->vehiculoId,
            'numero_cita' => $citaData['numero_cita'] ?? 'N/A',
            'raw_cita_data' => $citaData
        ]);

        // Validar que tenemos los datos necesarios
        if (empty($citaData['id'])) {
            Log::error('[DetalleVehiculo::editarCita] ID de cita no disponible');
            return;
        }

        // Preparar parámetros para el modo edición con mejor mapeo
        $editParams = [
            'vehiculoId' => $this->vehiculoId,
            'editMode' => 'true',
            'originalCitaId' => $citaData['numero_cita'] ?? $citaData['id'],
            'originalUuid' => $citaData['id'],
            'originalCenterId' => $this->mapearCentroIdParaEdicion($citaData),
            'originalDate' => $citaData['fecha_cita'] ?? '',
            'originalTime' => $citaData['hora_cita'] ?? '',
            'originalServicio' => $citaData['servicio'] ?? '',
            'originalSede' => $citaData['sede'] ?? ''
        ];

        Log::info('[DetalleVehiculo::editarCita] Datos de cita mapeados', [
            'raw_centro_id' => $citaData['centro_id'] ?? 'N/A',
            'raw_sede' => $citaData['sede'] ?? 'N/A',
            'mapped_centro_id' => $editParams['originalCenterId'],
            'fecha_cita' => $citaData['fecha_cita'] ?? 'N/A',
            'hora_cita' => $citaData['hora_cita'] ?? 'N/A'
        ]);

        Log::info('[DetalleVehiculo::editarCita] Redirigiendo a modo edición', $editParams);
        Log::info('🚀 [DetalleVehiculo::editarCita] URL generada: ' . AgendarCita::getUrl($editParams));

        // Redirigir a AgendarCita con parámetros de edición
        $this->redirect(AgendarCita::getUrl($editParams));
        
        Log::info('✅ [DetalleVehiculo::editarCita] ========== REDIRECCIÓN EJECUTADA ==========');
    }

    /**
     * Mapear centro_id de C4C a código de local para edición
     */
    protected function mapearCentroIdParaEdicion(array $citaData): string
    {
        // Mapeo basado en los datos disponibles
        $centroId = $citaData['centro_id'] ?? '';
        $sede = $citaData['sede'] ?? '';
        
        // Mapeo directo si ya viene el código correcto
        if (in_array($centroId, ['M013', 'M023', 'M303', 'M313', 'L013', 'L023'])) {
            return $centroId;
        }
        
        // Mapeo por nombre de sede
        $mapeoSedes = [
            'MOLINA' => 'M013',
            'CANADA' => 'M023', 
            'MIRAFLORES' => 'M303',
            'AREQUIPA' => 'M313',
            'LEXUS' => 'L013'
        ];
        
        foreach ($mapeoSedes as $nombreSede => $codigoLocal) {
            if (stripos($sede, $nombreSede) !== false) {
                Log::info('[DetalleVehiculo::mapearCentroIdParaEdicion] Mapeo encontrado', [
                    'sede_original' => $sede,
                    'codigo_mapeado' => $codigoLocal
                ]);
                return $codigoLocal;
            }
        }
        
        // Fallback: retornar el centro_id original
        Log::warning('[DetalleVehiculo::mapearCentroIdParaEdicion] No se pudo mapear centro', [
            'centro_id' => $centroId,
            'sede' => $sede
        ]);
        
        return $centroId;
    }

    /**
     * Anular una cita existente (eliminar en C4C)
     */
    public function anularCita(array $citaData): void
    {
        Log::info('🗑️ [DetalleVehiculo::anularCita] ========== INICIO ANULACIÓN CITA ==========');
        Log::info('🗑️ [DetalleVehiculo::anularCita] MÉTODO EJECUTADO - Button clicked!');
        Log::info('[DetalleVehiculo::anularCita] Iniciando anulación de cita', [
            'cita_id' => $citaData['id'] ?? 'N/A',
            'numero_cita' => $citaData['numero_cita'] ?? 'N/A',
            'vehiculo_id' => $this->vehiculoId,
            'status_raw' => $citaData['status_raw'] ?? 'N/A',
            'full_cita_data' => $citaData,
        ]);

        // Validar que tenemos los datos necesarios
        if (empty($citaData['id'])) {
            Log::error('[DetalleVehiculo::anularCita] UUID de cita no disponible');
            
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('No se puede anular la cita: ID no disponible.')
                ->danger()
                ->send();
            return;
        }

        $uuid = $citaData['id'];
        $statusRaw = $citaData['status_raw'] ?? '1';

        // Validaciones de negocio - Solo permitir anular citas en estado 1 (Generada) o 2 (Confirmada)
        if (!in_array($statusRaw, ['1', '2'])) {
            $estadoNombre = $this->mapearEstadoCitaC4C($statusRaw);
            
            Log::warning('[DetalleVehiculo::anularCita] Intento de anular cita en estado no válido', [
                'status_raw' => $statusRaw,
                'estado_nombre' => $estadoNombre,
            ]);
            
            \Filament\Notifications\Notification::make()
                ->title('No se puede anular')
                ->body("No se puede anular una cita en estado: {$estadoNombre}. Solo se pueden anular citas Generadas o Confirmadas.")
                ->warning()
                ->send();
            return;
        }

        // Buscar la cita en la base de datos local para validaciones adicionales
        $appointment = Appointment::where('c4c_uuid', $uuid)->first();
        
        if ($appointment) {
            // Validar estado local también
            if (!in_array($appointment->status, ['pending', 'confirmed', 'generated'])) {
                Log::warning('[DetalleVehiculo::anularCita] Estado local no válido para anulación', [
                    'local_status' => $appointment->status,
                ]);
                
                \Filament\Notifications\Notification::make()
                    ->title('No se puede anular')
                    ->body("El estado local de la cita ({$appointment->status}) no permite la anulación.")
                    ->warning()
                    ->send();
                return;
            }

            // Verificar que no esté ya en proceso de eliminación
            if (in_array($appointment->status, ['deleting', 'delete_failed', 'deleted'])) {
                \Filament\Notifications\Notification::make()
                    ->title('Cita ya procesada')
                    ->body('La cita ya está siendo eliminada o ya fue eliminada.')
                    ->warning()
                    ->send();
                return;
            }
        }

        // Mostrar confirmación al usuario
        $this->dispatch('show-delete-confirmation', 
            $uuid,
            [
                'numero_cita' => $citaData['numero_cita'] ?? 'N/A',
                'fecha_cita' => $citaData['fecha_cita'] ?? 'N/A',
                'hora_cita' => $citaData['hora_cita'] ?? 'N/A',
            ]
        );
    }

    /**
     * Confirmar la anulación después de la confirmación del usuario
     */
    public function confirmarAnulacion(string $uuid, array $citaData): void
    {
        Log::info('[DetalleVehiculo::confirmarAnulacion] Confirmación recibida, procediendo con anulación', [
            'uuid' => $uuid,
        ]);

        try {
            // Buscar el appointment local si existe
            $appointment = Appointment::where('c4c_uuid', $uuid)->first();
            
            // Si no se encuentra por UUID exacto, buscar por patrón similar
            if (!$appointment) {
                Log::info('[DetalleVehiculo::confirmarAnulacion] UUID exacto no encontrado, buscando por patrón', [
                    'uuid_buscado' => $uuid,
                ]);
                
                // Extraer las primeras partes del UUID para búsqueda más flexible
                // Usamos solo las primeras 3 secciones del UUID para mayor flexibilidad
                $uuidBase = substr($uuid, 0, 18); // b7d671af-46bb-1fd0
                Log::info('[DetalleVehiculo::confirmarAnulacion] Patrón de búsqueda generado', [
                    'uuid_original' => $uuid,
                    'patron_busqueda' => $uuidBase . '%',
                ]);
                
                $appointment = Appointment::where('c4c_uuid', 'LIKE', $uuidBase . '%')->first();
                
                if ($appointment) {
                    Log::info('[DetalleVehiculo::confirmarAnulacion] Appointment encontrado por patrón UUID', [
                        'uuid_buscado' => $uuid,
                        'uuid_encontrado' => $appointment->c4c_uuid,
                        'appointment_id' => $appointment->id,
                    ]);
                } else {
                    Log::warning('[DetalleVehiculo::confirmarAnulacion] No se encontró appointment con patrón UUID', [
                        'uuid_buscado' => $uuid,
                        'patron_busqueda' => $uuidBase . '%',
                    ]);
                }
            }
            
            if ($appointment) {
                // Marcar como en proceso de eliminación
                $appointment->update([
                    'status' => 'deleting',
                    'c4c_status' => 'deleting',
                ]);
                
                Log::info('[DetalleVehiculo::confirmarAnulacion] Appointment marcado como deleting', [
                    'appointment_id' => $appointment->id,
                ]);

                // **ENVIAR EMAIL DE CITA CANCELADA** 📧
                $this->enviarEmailCitaCancelada($appointment, 'Cita anulada por solicitud del cliente');
            }

            // Generar job ID único para tracking
            $jobId = Str::uuid()->toString();
            
            // Debug: verificar qué appointment se está enviando al job
            $appointmentId = $appointment?->id ?? 0;
            Log::info('[DetalleVehiculo::confirmarAnulacion] Preparando dispatch del job', [
                'appointment_object' => $appointment ? 'exists' : 'null',
                'appointment_id_computed' => $appointmentId,
                'uuid' => $uuid,
                'job_id' => $jobId,
            ]);

            // Disparar job asíncrono para eliminación en C4C
            DeleteAppointmentC4CJob::dispatch($uuid, $appointmentId, $jobId)
                ->onQueue('c4c-delete');

            Log::info('[DetalleVehiculo::confirmarAnulacion] Job de eliminación disparado', [
                'job_id' => $jobId,
                'uuid' => $uuid,
            ]);

            // Mostrar notificación de éxito
            \Filament\Notifications\Notification::make()
                ->title('Anulación iniciada')
                ->body('Se ha iniciado el proceso de anulación de la cita. La cita será eliminada en unos momentos.')
                ->success()
                ->send();

            // Actualizar la vista removiendo la cita de la lista local temporalmente
            $this->removerCitaDeVista($uuid);

            // Opcional: Recargar citas después de un delay
            $this->dispatch('reload-citas-after-delay');

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo::confirmarAnulacion] Error al confirmar anulación', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);

            // Revertir el estado si hubo error
            if ($appointment) {
                $appointment->update([
                    'status' => $appointment->getOriginal('status'),
                    'c4c_status' => $appointment->getOriginal('c4c_status'),
                ]);
            }

            \Filament\Notifications\Notification::make()
                ->title('Error al anular')
                ->body('Error al iniciar la anulación: ' . $e->getMessage())
                ->danger()
                ->send();
        }

        Log::info('✅ [DetalleVehiculo::confirmarAnulacion] ========== ANULACIÓN PROCESADA ==========');
    }

    /**
     * Remover cita de la vista temporalmente (actualización optimista de UI)
     */
    private function removerCitaDeVista(string $uuid): void
    {
        try {
            // Filtrar las citas agendadas para remover la que se está anulando
            $this->citasAgendadas = array_filter($this->citasAgendadas, function($cita) use ($uuid) {
                return ($cita['id'] ?? '') !== $uuid;
            });

            // Reindexar el array
            $this->citasAgendadas = array_values($this->citasAgendadas);

            Log::info('[DetalleVehiculo::removerCitaDeVista] Cita removida de la vista', [
                'uuid' => $uuid,
                'citas_restantes' => count($this->citasAgendadas),
            ]);

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo::removerCitaDeVista] Error al remover cita de vista', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determinar el tipo de servicio basado en los datos de la cita
     */
    protected function determinarTipoServicio(?string $maintenanceType, ?string $serviceMode): string
    {
        if ($maintenanceType) {
            return match ($maintenanceType) {
                'mantenimiento_5000' => 'Mantenimiento 5,000 Km',
                'mantenimiento_10000' => 'Mantenimiento 10,000 Km',
                'mantenimiento_15000' => 'Mantenimiento 15,000 Km',
                'mantenimiento_20000' => 'Mantenimiento 20,000 Km',
                'mantenimiento_25000' => 'Mantenimiento 25,000 Km',
                'mantenimiento_30000' => 'Mantenimiento 30,000 Km',
                'reparacion_general' => 'Reparación General',
                'diagnostico' => 'Diagnóstico',
                'revision_tecnica' => 'Revisión Técnica',
                default => ucfirst(str_replace('_', ' ', $maintenanceType)),
            };
        }

        if ($serviceMode) {
            return match ($serviceMode) {
                'express' => 'Servicio Express',
                'normal' => 'Servicio Normal',
                'premium' => 'Servicio Premium',
                default => ucfirst($serviceMode),
            };
        }

        return 'Servicio no especificado';
    }

    /**
     * Mapear el estado de la cita a un formato legible
     */
    protected function mapearEstadoCita(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmada',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
            default => ucfirst($status),
        };
    }

    /**
     * Determinar el tipo de pago basado en los datos de la cita
     */
    protected function determinarTipoPago(Appointment $cita): string
    {
        // Si tiene package_id, probablemente es prepagado
        if (!empty($cita->package_id)) {
            return 'Prepagado';
        }

        // Si tiene comentarios que indican prepago
        if ($cita->comments && str_contains(strtolower($cita->comments), 'prepag')) {
            return 'Prepagado';
        }

        return 'Por definir';
    }

    /**
     * Determinar el tipo de servicio basado en los datos de C4C
     */
    protected function determinarTipoServicioC4C(array $cita): string
    {
        // Usar el subject_name como base para el tipo de servicio
        $subjectName = $cita['subject_name'] ?? '';

        if (!empty($subjectName)) {
            // Extraer información útil del subject_name
            if (str_contains(strtolower($subjectName), 'mantenimiento')) {
                return 'Mantenimiento Programado';
            }
            if (str_contains(strtolower($subjectName), 'reparacion')) {
                return 'Reparación';
            }
            if (str_contains(strtolower($subjectName), 'revision')) {
                return 'Revisión Técnica';
            }
            if (str_contains(strtolower($subjectName), 'diagnostico')) {
                return 'Diagnóstico';
            }

            // Si no coincide con patrones conocidos, usar el subject_name limpio
            return $subjectName;
        }

        // Fallback basado en otros campos
        if (!empty($cita['vehicle_model'])) {
            return 'Servicio para ' . $cita['vehicle_model'];
        }

        return 'Servicio programado';
    }

    /**
     * Mapear el estado de la cita de C4C a formato legible
     */
    protected function mapearEstadoCitaC4C(string $appointmentStatus): string
    {
        return match ($appointmentStatus) {
            '1' => 'Generada',
            '2' => 'Confirmada',
            '3' => 'En proceso',
            '4' => 'Diferida',
            '5' => 'Completada',
            '6' => 'Cancelada',
            default => 'Estado ' . $appointmentStatus,
        };
    }

    /**
     * Formatear fecha de C4C (formato YYYY-MM-DD)
     */
    protected function formatearFechaC4C(string $fecha): string
    {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return '-';
        }

        try {
            $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
            return $fechaObj ? $fechaObj->format('d/m/Y') : '-';
        } catch (\Exception $e) {
            return '-';
        }
    }

    /**
     * Formatear hora de C4C
     * CORREGIDO: Los datos de C4C ya vienen en hora local de Perú, no necesitan conversión de zona horaria
     */
    protected function formatearHoraC4C(string $hora): string
    {
        if (empty($hora)) {
            return '';
        }

        try {
            // CORREGIDO: Parsear la hora directamente sin conversión de zona horaria
            // Los datos de C4C ya están en hora local de Perú
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $hora)) {
                // Si viene en formato HH:MM o HH:MM:SS, extraer solo HH:MM
                $partes = explode(':', $hora);
                $horas = str_pad($partes[0], 2, '0', STR_PAD_LEFT);
                $minutos = str_pad($partes[1] ?? '00', 2, '0', STR_PAD_LEFT);
                return $horas . ':' . $minutos;
            }
            
            // Si viene como "00", mostrar como hora válida
            if ($hora === '00') {
                return '00:00';
            }

            // Si viene como "00:00:00", mostrar solo HH:MM
            if ($hora === '00:00:00') {
                return '00:00';
            }

            // Si viene solo como número (ej: "14"), convertir a formato hora
            if (is_numeric($hora) && strlen($hora) <= 2) {
                return str_pad($hora, 2, '0', STR_PAD_LEFT) . ':00';
            }

            // Fallback: intentar parsear con Carbon sin conversión de zona horaria
            $horaCarbon = \Carbon\Carbon::createFromFormat('H:i:s', $hora);
            return $horaCarbon->format('H:i');
            
        } catch (\Exception $e) {
            // Si hay algún error, intentar con el formato antiguo como fallback
            try {
                // Si viene en formato HH:MM:SS, extraer solo HH:MM
                if (str_contains($hora, ':')) {
                    $partes = explode(':', $hora);
                    return $partes[0] . ':' . ($partes[1] ?? '00');
                }

                return $hora;
            } catch (\Exception $e) {
                return '';
            }
        }
    }

    /**
     * Formatear fecha y hora de entrega estimada de C4C
     */
    protected function formatearFechaHoraEntregaC4C(array $cita): string
    {
        $fechaSalida = $cita['exit_date'] ?? $cita['scheduled_end_date'] ?? '';
        $horaSalida = $cita['exit_time'] ?? '';

        if (empty($fechaSalida)) {
            return '-';
        }

        $fechaFormateada = $this->formatearFechaC4C($fechaSalida);
        $horaFormateada = $this->formatearHoraC4C($horaSalida);

        if ($fechaFormateada === '-') {
            return '-';
        }

        if ($horaFormateada === '-') {
            return $fechaFormateada;
        }

        return $fechaFormateada . ' ' . $horaFormateada;
    }

    /**
     * Obtener información completa del estado para mostrar dinámicamente
     * Ahora incluye lógica basada en datos SAP
     */
    protected function obtenerInformacionEstadoCompleta(string $appointmentStatus, array $currentAppointmentData = null): array
    {
        Log::info('📊 [ESTADO-FLOW] === MÉTODO obtenerInformacionEstadoCompleta EJECUTÁNDOSE ===', [
            'appointment_status' => $appointmentStatus,
            'citas_agendadas_count' => count($this->citasAgendadas),
            'tiene_datos_sap' => !empty($this->datosAsesorSAP),
            'current_appointment_provided' => !empty($currentAppointmentData),
            'current_appointment_keys' => $currentAppointmentData ? array_keys($currentAppointmentData) : 'null'
        ]);
        
        // Log adicional para ver el contenido completo de currentAppointmentData
        if ($currentAppointmentData) {
            Log::info('📊 [ESTADO-FLOW] Contenido de currentAppointmentData', [
                'data' => $currentAppointmentData
            ]);
        }
        
        $estados = [
            '1' => [
                'codigo' => '1',
                'nombre' => 'Generada',
                'etapas' => [
                    'cita_confirmada' => ['activo' => false, 'completado' => true],
                    'en_trabajo' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'trabajo_concluido' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'entregado' => ['activo' => false, 'completado' => false],
                ]
            ],
            '2' => [
                'codigo' => '2',
                'nombre' => 'Confirmada',
                'etapas' => [
                    'cita_confirmada' => ['activo' => false, 'completado' => true],
                    'en_trabajo' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'trabajo_concluido' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'entregado' => ['activo' => false, 'completado' => false],
                ]
            ],
            '3' => [
                'codigo' => '3',
                'nombre' => 'En proceso',
                'etapas' => [
                    'cita_confirmada' => ['activo' => false, 'completado' => true],
                    'en_trabajo' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'trabajo_concluido' => ['activo' => false, 'completado' => false], // Estados iniciales VACÍOS
                    'entregado' => ['activo' => false, 'completado' => false],
                ]
            ],
            '4' => [
                'codigo' => '4',
                'nombre' => 'Diferida',
                'etapas' => [
                    'cita_confirmada' => ['activo' => true, 'completado' => true],
                    'en_trabajo' => ['activo' => false, 'completado' => false],
                    'trabajo_concluido' => ['activo' => false, 'completado' => false],
                    'entregado' => ['activo' => false, 'completado' => false],
                ]
            ],
            '5' => [
                'codigo' => '5',
                'nombre' => 'Completada',
                'etapas' => [
                    'cita_confirmada' => ['activo' => false, 'completado' => true],
                    'en_trabajo' => ['activo' => false, 'completado' => true],
                    'trabajo_concluido' => ['activo' => false, 'completado' => true],
                    'entregado' => ['activo' => true, 'completado' => true],
                ]
            ],
            '6' => [
                'codigo' => '6',
                'nombre' => 'Cancelada',
                'etapas' => [
                    'cita_confirmada' => ['activo' => false, 'completado' => false],
                    'en_trabajo' => ['activo' => false, 'completado' => false],
                    'trabajo_concluido' => ['activo' => false, 'completado' => false],
                    'entregado' => ['activo' => false, 'completado' => false],
                ]
            ],
        ];

        $estadoBase = $estados[$appointmentStatus] ?? $estados['1'];
        
        Log::info('📊 [ESTADO-FLOW] Estado base inicial', [
            'codigo' => $estadoBase['codigo'],
            'nombre' => $estadoBase['nombre'],
            'etapas' => $estadoBase['etapas']
        ]);

        // Si tenemos un UUID en los datos de la cita, intentar obtener los estados frontend de la BD
        $uuid = null;
        if ($currentAppointmentData) {
            $uuid = $currentAppointmentData['uuid'] ?? $currentAppointmentData['id'] ?? null;
            if ($uuid) {
                $frontendStatesFromDB = $this->obtenerEstadosFrontendDeBD($uuid);
                if ($frontendStatesFromDB) {
                    // Usar los estados frontend de la BD
                    $estadoBase['etapas']['cita_confirmada'] = $frontendStatesFromDB['cita_confirmada'];
                    $estadoBase['etapas']['en_trabajo'] = $frontendStatesFromDB['en_trabajo'];
                    $estadoBase['etapas']['trabajo_concluido'] = $frontendStatesFromDB['trabajo_concluido'];
                    
                    Log::info('📊 [ESTADO-FLOW] Estados frontend cargados desde BD', [
                        'uuid' => $uuid,
                        'etapas' => $estadoBase['etapas']
                    ]);
                    
                    // Retornar el estado con los estados frontend de la BD
                    return $estadoBase;
                }
            }
        }

        // Aplicar lógica SAP para modificar estados dinámicamente
        if ($this->datosAsesorSAP) {
            Log::info('📊 [ESTADO-FLOW] Aplicando lógica SAP a estado');
            $estadoBase = $this->aplicarLogicaSAPAEstado($estadoBase, $currentAppointmentData);
            Log::info('📊 [ESTADO-FLOW] Estado base después de lógica SAP', [
                'codigo' => $estadoBase['codigo'],
                'nombre' => $estadoBase['nombre'],
                'etapas' => $estadoBase['etapas']
            ]);
        } else {
            Log::info('📊 [ESTADO-FLOW] NO se aplica lógica SAP - datosAsesorSAP está vacío');
        }

        return $estadoBase;
    }

    /**
     * Aplicar lógica SAP para modificar estados dinámicamente
     * Lógica progresiva según el proceso actual
     */
    protected function aplicarLogicaSAPAEstado(array $estadoBase, ?array $currentAppointmentData = null): array
    {
        // Log inicial para saber que el método se está ejecutando
        Log::info("[DetalleVehiculo] === INICIO aplicarLogicaSAPAEstado ===");
        
        // Obtener datos de SAP
        $tieneFechaUltServ = $this->datosAsesorSAP['tiene_fecha_ult_serv'] ?? false;
        $tieneFechaFactura = $this->datosAsesorSAP['tiene_fecha_factura'] ?? false;
        $fechaUltServ = $this->datosAsesorSAP['fecha_ult_serv'] ?? null;
        
        // Log detallado de datos SAP para depuración
        Log::info("[DetalleVehiculo] Datos SAP obtenidos para evaluación de estados", [
            'tiene_fecha_ult_serv' => $tieneFechaUltServ ? 'SÍ' : 'NO',
            'fecha_ult_serv' => $fechaUltServ ?? 'No disponible',
            'tiene_fecha_factura' => $tieneFechaFactura ? 'SÍ' : 'NO',
            'fecha_factura' => $this->datosAsesorSAP['fecha_factura'] ?? 'No disponible',
            'datosAsesorSAP_completo' => $this->datosAsesorSAP
        ]);
        
        // Resetear TODOS los estados de frontend independientemente del estado C4C
        // Los estados "en_trabajo" y "trabajo_concluido" deben depender EXCLUSIVAMENTE de los datos SAP
        // El estado C4C solo determina la visibilidad de la cita, no los estados de frontend
        $estadoBase['etapas']['en_trabajo']['activo'] = false;
        $estadoBase['etapas']['en_trabajo']['completado'] = false;
        $estadoBase['etapas']['trabajo_concluido']['activo'] = false;
        $estadoBase['etapas']['trabajo_concluido']['completado'] = false;
        
        Log::info("[DetalleVehiculo] Estados de frontend reseteados - independientes de estado C4C", [
            'estado_c4c' => $estadoBase['codigo'],
            'nombre_c4c' => $estadoBase['nombre'],
            'en_trabajo_activo' => $estadoBase['etapas']['en_trabajo']['activo'] ? 'SÍ' : 'NO',
            'trabajo_concluido_activo' => $estadoBase['etapas']['trabajo_concluido']['activo'] ? 'SÍ' : 'NO',
        ]);
        
        // Usar los datos de la cita actual si están disponibles
        $fechaCitaActual = null;
        if ($currentAppointmentData) {
            // Intentar obtener fecha de diferentes campos
            if (isset($currentAppointmentData['scheduled_start_date'])) {
                $fechaCitaActual = $currentAppointmentData['scheduled_start_date'];
            } elseif (isset($currentAppointmentData['appointment_date'])) {
                $fechaCitaActual = $currentAppointmentData['appointment_date'];
            }
            
            // Normalizar fecha si se obtuvo
            if ($fechaCitaActual) {
                // Si es objeto Carbon, convertir a string
                if (is_object($fechaCitaActual) && method_exists($fechaCitaActual, 'format')) {
                    $fechaCitaActual = $fechaCitaActual->format('Y-m-d');
                }
                // Si es string, normalizar formato
                elseif (is_string($fechaCitaActual)) {
                    $fechaCitaActual = substr($fechaCitaActual, 0, 10); // Solo YYYY-MM-DD
                }
            }
        }
        
        // FALLBACK: Solo si no se obtuvo fecha de currentAppointmentData, usar método anterior
        if (!$fechaCitaActual) {
            // Obtener la fecha de la cita del array de citas transformado
            $citaActual = $this->citasAgendadas[0] ?? null;
            
            // Intentar obtener el ID de diferentes maneras
            $citaId = null;
            $candidatosId = [];
            
            // 1. Verificar si el ID está en el formato numérico directo
            if (isset($citaActual['id']) && is_numeric($citaActual['id'])) {
                $candidatosId[] = (int)$citaActual['id'];
            } 
            // 2. Verificar si el ID está en el formato 'local-123'
            elseif (isset($citaActual['id']) && strpos($citaActual['id'], 'local-') === 0) {
                $candidatosId[] = (int)substr($citaActual['id'], 6);
            }
            // 3. Verificar si hay un número de cita disponible
            if (isset($citaActual['numero_cita'])) {
                if (is_numeric($citaActual['numero_cita'])) {
                    $candidatosId[] = (int)$citaActual['numero_cita'];
                } elseif (is_string($citaActual['numero_cita']) && strpos($citaActual['numero_cita'], 'CITA-') === 0) {
                    $candidatosId[] = (int)substr($citaActual['numero_cita'], 5);
                }
            }
            
            // Buscar cita en la base de datos
            foreach ($candidatosId as $index => $id) {
                $citaLocal = \App\Models\Appointment::find($id);
                if ($citaLocal) {
                    $fechaCitaActual = $citaLocal->appointment_date ? $citaLocal->appointment_date->format('Y-m-d') : null;
                    break; // Usar el primer ID que encuentre
                }
            }
            
            // Si no se pudo obtener de la base de datos local, usar el valor del array
            if (!$fechaCitaActual) {
                $fechaCitaActual = $citaActual['fecha_cita'] ?? null;
                if ($fechaCitaActual) {
                    // Intentar convertir formato d/m/Y a Y-m-d si es necesario
                    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fechaCitaActual)) {
                        try {
                            $fechaCitaActual = \Carbon\Carbon::createFromFormat('d/m/Y', $fechaCitaActual)->format('Y-m-d');
                        } catch (\Exception $e) {
                            // Mantener fecha original si hay error
                        }
                    }
                }
            }
        }
        
        // Asegurarse de que las fechas estén en el mismo formato para comparación (YYYY-MM-DD)
        if ($fechaUltServ) {
            $fechaUltServ = substr($fechaUltServ, 0, 10);
        }
        
        if ($fechaCitaActual) {
            $fechaCitaActual = substr($fechaCitaActual, 0, 10);
        }
        
        // Log para verificar las fechas que se están usando
        Log::info("[DetalleVehiculo] Fechas para evaluación", [
            'fecha_ult_serv' => $fechaUltServ ?? 'No disponible',
            'fecha_cita_actual' => $fechaCitaActual ?? 'No disponible'
        ]);
        
        // CASO 1: TRABAJO CONCLUIDO - Si tiene fecha de factura y es MAYOR O IGUAL a la fecha de cita
        Log::info("[DetalleVehiculo] Evaluando estado TRABAJO CONCLUIDO", [
            'tiene_fecha_factura' => $tieneFechaFactura ? 'SÍ' : 'NO'
        ]);
        
        if ($tieneFechaFactura) {
            $fechaFactura = $this->datosAsesorSAP['fecha_factura'] ?? '';
            
            Log::info("[DetalleVehiculo] Evaluando trabajo concluido con fechas", [
                'fecha_factura' => $fechaFactura,
                'fecha_cita_actual' => $fechaCitaActual
            ]);
            
            // Verificar que la fecha de factura sea mayor o igual a la fecha de cita
            if ($fechaFactura && $fechaCitaActual && 
                $this->fechaSAPMayorOIgualACita($fechaFactura, $fechaCitaActual)) {
                
                $estadoBase['etapas']['cita_confirmada']['activo'] = false;
                $estadoBase['etapas']['cita_confirmada']['completado'] = true;
                
                $estadoBase['etapas']['en_trabajo']['activo'] = false;
                $estadoBase['etapas']['en_trabajo']['completado'] = true;
                
                $estadoBase['etapas']['trabajo_concluido']['activo'] = true;
                $estadoBase['etapas']['trabajo_concluido']['completado'] = true;
                
                // Log para depuración de trabajo concluido
                Log::info("[DetalleVehiculo] Estado 'Trabajo concluido' activado por fecha de factura mayor o igual a fecha de cita", [
                    'fecha_factura' => $fechaFactura, 
                    'fecha_cita' => $fechaCitaActual,
                    'evaluacion' => 'Fecha de factura SAP mayor o igual a la fecha de cita'
                ]);
                
                return $estadoBase;
            }
            // Si tiene fecha de factura pero NO cumple la condición, resetear estado
            else {
                // Resetear a estado por defecto basado en el appointmentStatus original
                // Mantener el estado original del appointment
                Log::info("[DetalleVehiculo] Estado 'Trabajo concluido' NO activado: fecha factura NO es mayor o igual a fecha cita", [
                    'fecha_factura' => $fechaFactura, 
                    'fecha_cita' => $fechaCitaActual
                ]);
            }
        } else {
            Log::info("[DetalleVehiculo] No se evalúa trabajo concluido porque no tiene fecha de factura");
        }
        
        // CASO 2: EN TRABAJO - Si tiene fecha de servicio reciente que coincide con la fecha de cita
        Log::info("[DetalleVehiculo] Evaluando estado EN TRABAJO", [
            'tiene_fecha_ult_serv' => $tieneFechaUltServ ? 'SÍ' : 'NO',
            'fecha_ult_serv' => $fechaUltServ ?? 'No disponible'
        ]);
        
        if ($tieneFechaUltServ && $fechaUltServ) {
            // Verificar si la fecha de servicio es igual a la fecha de la cita
            Log::info("[DetalleVehiculo] Evaluando si fecha de servicio coincide con fecha de cita", [
                'fecha_ult_serv' => $fechaUltServ,
                'fecha_cita' => $fechaCitaActual,
                'son_iguales' => ($fechaCitaActual && $fechaUltServ == $fechaCitaActual) ? 'SÍ' : 'NO',
                'fecha_ult_serv_normalizada' => $this->normalizarFecha($fechaUltServ),
                'fecha_cita_normalizada' => $this->normalizarFecha($fechaCitaActual)
            ]);
            
            // Usando normalización de fechas para comparación más confiable
            $fechaUltServNormalizada = $this->normalizarFecha($fechaUltServ);
            $fechaCitaNormalizada = $this->normalizarFecha($fechaCitaActual);
            
            if ($fechaCitaActual && $fechaUltServNormalizada && $fechaCitaNormalizada && 
                $fechaUltServNormalizada === $fechaCitaNormalizada) {
                $estadoBase['etapas']['cita_confirmada']['activo'] = false;
                $estadoBase['etapas']['cita_confirmada']['completado'] = true;
                
                $estadoBase['etapas']['en_trabajo']['activo'] = true;
                $estadoBase['etapas']['en_trabajo']['completado'] = false;
                
                $estadoBase['etapas']['trabajo_concluido']['activo'] = false;
                $estadoBase['etapas']['trabajo_concluido']['completado'] = false;
                
                // Log para depuración de en trabajo
                Log::info("[DetalleVehiculo] Estado 'En trabajo' activado y 'Trabajo concluido' desactivado", [
                    'fecha_ult_serv' => $fechaUltServ, 
                    'fecha_cita' => $fechaCitaActual
                ]);
                
                return $estadoBase;
            } else {
                // Log para depuración cuando no coinciden las fechas
                Log::info("[DetalleVehiculo] Estado 'En trabajo' NO activado: fechas no coinciden", [
                    'fecha_ult_serv' => $fechaUltServ,
                    'fecha_ult_serv_normalizada' => $fechaUltServNormalizada,
                    'fecha_cita' => $fechaCitaActual,
                    'fecha_cita_normalizada' => $fechaCitaNormalizada,
                    'coinciden' => ($fechaCitaActual && $fechaUltServNormalizada && $fechaCitaNormalizada && $fechaUltServNormalizada === $fechaCitaNormalizada) ? 'SÍ' : 'NO'
                ]);
            }
        } else {
            Log::info("[DetalleVehiculo] No se evalúa estado 'En trabajo'", [
                'tiene_fecha_ult_serv' => $tieneFechaUltServ ? 'SÍ' : 'NO',
                'fecha_ult_serv' => $fechaUltServ ?? 'No disponible'
            ]);
        }
        
        // Si no se ha aplicado ningún estado especial, mantener el estado reseteado
        Log::info("[DetalleVehiculo] No se ha aplicado ningún estado especial SAP, manteniendo estado reseteado", [
            'tiene_fecha_ult_serv' => $tieneFechaUltServ ? 'SÍ' : 'NO',
            'fecha_ult_serv' => $fechaUltServ ?? 'No disponible',
            'tiene_fecha_factura' => $tieneFechaFactura ? 'SÍ' : 'NO', 
            'fecha_cita_actual' => $fechaCitaActual ?? 'No disponible',
            'comparacion_fecha_ult_serv_igual_cita' => ($fechaCitaActual && $fechaUltServ == $fechaCitaActual) ? 'SÍ' : 'NO'
        ]);
        
        Log::info("[DetalleVehiculo] === FIN aplicarLogicaSAPAEstado ===");
        
        return $estadoBase;
    }
    
    /**
     * Compara si dos fechas son iguales, independientemente de su formato
     * @param string|null $fechaSAP Fecha de SAP (formato YYYY-MM-DD)
     * @param string|null $fechaCita Fecha de la cita (puede estar en formato d/m/Y o YYYY-MM-DD)
     * @return bool
     */
    protected function fechasCoinciden(?string $fechaSAP, ?string $fechaCita): bool
    {
        if (empty($fechaSAP) || empty($fechaCita)) {
            return false;
        }

        try {
            // Intentar parsear las fechas con Carbon
            $carbonSAP = null;
            $carbonCita = null;
            $formatos = ['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s', 'd/m/Y H:i:s', 'Ymd'];
            
            // Intentar parsear fecha SAP
            foreach ($formatos as $formato) {
                try {
                    $carbonSAP = \Carbon\Carbon::createFromFormat($formato, $fechaSAP);
                    if ($carbonSAP) break;
                } catch (\Exception $e) {
                    // Continuar con el siguiente formato
                    continue;
                }
            }
            
            // Intentar parsear fecha Cita
            foreach ($formatos as $formato) {
                try {
                    $carbonCita = \Carbon\Carbon::createFromFormat($formato, $fechaCita);
                    if ($carbonCita) break;
                } catch (\Exception $e) {
                    // Continuar con el siguiente formato
                    continue;
                }
            }
            
            // Si no se pudo parsear alguna fecha, intentar con parse genérico
            if (!$carbonSAP) {
                try {
                    $carbonSAP = \Carbon\Carbon::parse($fechaSAP);
                } catch (\Exception $e) {
                    // No se pudo parsear
                }
            }
            
            if (!$carbonCita) {
                try {
                    $carbonCita = \Carbon\Carbon::parse($fechaCita);
                } catch (\Exception $e) {
                    // No se pudo parsear
                }
            }
            
            if (!$carbonSAP || !$carbonCita) {
                return false;
            }
            
            // Normalizar a fecha sin hora para comparación
            $fechaSAPNormalizada = $carbonSAP->format('Y-m-d');
            $fechaCitaNormalizada = $carbonCita->format('Y-m-d');
            
            // Comparar las fechas normalizadas
            $coinciden = $fechaSAPNormalizada === $fechaCitaNormalizada;
            
            return $coinciden;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Compara si una fecha SAP es mayor o igual a la fecha de cita
     * @param string|null $fechaSAP Fecha de SAP (formato YYYY-MM-DD)
     * @param string|null $fechaCita Fecha de la cita (puede estar en formato d/m/Y o YYYY-MM-DD)
     * @return bool
     */
    protected function fechaSAPMayorOIgualACita(?string $fechaSAP, ?string $fechaCita): bool
    {
        Log::info('[DetalleVehiculo] Comparando fechas para Trabajo concluido', [
            'fecha_sap_original' => $fechaSAP,
            'fecha_cita_original' => $fechaCita
        ]);
        
        if (empty($fechaSAP) || empty($fechaCita)) {
            Log::warning('[DetalleVehiculo] Una de las fechas está vacía', [
                'fecha_sap' => $fechaSAP,
                'fecha_cita' => $fechaCita
            ]);
            return false;
        }

        try {
            // Normalizar fechas al formato Y-m-d
            $fechaSAPNormalizada = $this->normalizarFecha($fechaSAP);
            $fechaCitaNormalizada = $this->normalizarFecha($fechaCita);
            
            Log::info('[DetalleVehiculo] Fechas normalizadas para comparación', [
                'fecha_sap_normalizada' => $fechaSAPNormalizada,
                'fecha_cita_normalizada' => $fechaCitaNormalizada
            ]);
            
            if (!$fechaSAPNormalizada || !$fechaCitaNormalizada) {
                Log::warning('[DetalleVehiculo] No se pudo normalizar alguna de las fechas', [
                    'fecha_sap_original' => $fechaSAP,
                    'fecha_cita_original' => $fechaCita
                ]);
                return false;
            }

            // Comparación explícita usando strcmp para fechas en formato Y-m-d (YYYY-MM-DD)
            // De esta manera evitamos cualquier problema con Carbon o zonas horarias
            $resultado = strcmp($fechaSAPNormalizada, $fechaCitaNormalizada);
            
            // resultado >= 0 significa que fechaSAP es igual o mayor que fechaCita
            $esMayorOIgual = $resultado >= 0;
            
            // Mejoramos el log para depuración con valores exactos
            Log::info('[DetalleVehiculo] Comparación de fechas para Trabajo concluido', [
                'fecha_sap' => $fechaSAPNormalizada,
                'fecha_cita' => $fechaCitaNormalizada,
                'sap_mayor_igual_cita' => $esMayorOIgual ? 'SÍ' : 'NO',
                'resultado_strcmp' => $resultado,
                'razon' => $resultado == 0 ? 'Fechas iguales' : ($resultado > 0 ? 'Fecha SAP mayor que fecha cita' : 'Fecha SAP menor que fecha cita')
            ]);
            
            return $esMayorOIgual;
            
        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error comparando fechas', [
                'error' => $e->getMessage(),
                'fecha_sap' => $fechaSAP,
                'fecha_cita' => $fechaCita
            ]);
            return false;
        }
    }
    
    /**
     * Normaliza una fecha a formato Y-m-d para comparaciones consistentes
     * @param string|null $fecha La fecha en cualquier formato
     * @return string|null Fecha en formato Y-m-d o null si no se pudo normalizar
     */
    protected function normalizarFecha(?string $fecha): ?string
    {
        if (empty($fecha)) {
            return null;
        }
        
        Log::info('[DetalleVehiculo] Normalizando fecha', [
            'fecha_original' => $fecha,
            'tipo' => gettype($fecha)
        ]);
        
        // Si ya tiene formato Y-m-d, retornarlo directamente
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            Log::info('[DetalleVehiculo] Fecha ya en formato Y-m-d, retornando directamente', [
                'fecha' => $fecha
            ]);
            return $fecha;
        }
        
        // Si tiene formato d/m/Y, convertirlo
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $fecha)) {
            try {
                $partes = explode('/', $fecha);
                if (count($partes) === 3) {
                    $fechaNormalizada = $partes[2] . '-' . $partes[1] . '-' . $partes[0];
                    Log::info('[DetalleVehiculo] Fecha normalizada de d/m/Y a Y-m-d', [
                        'original' => $fecha,
                        'normalizada' => $fechaNormalizada
                    ]);
                    return $fechaNormalizada;
                }
            } catch (\Exception $e) {
                // Si falla, continuar con los otros métodos
                Log::warning('[DetalleVehiculo] Error al normalizar fecha d/m/Y', [
                    'fecha' => $fecha,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Intentar con Carbon en diferentes formatos
        $formatos = ['Y-m-d', 'd/m/Y', 'Ymd', 'Y-m-d H:i:s', 'd/m/Y H:i:s'];
        foreach ($formatos as $formato) {
            try {
                $fechaObj = \Carbon\Carbon::createFromFormat($formato, $fecha);
                if ($fechaObj) {
                    $fechaNormalizada = $fechaObj->format('Y-m-d');
                    Log::info('[DetalleVehiculo] Fecha normalizada con Carbon', [
                        'original' => $fecha,
                        'formato' => $formato,
                        'normalizada' => $fechaNormalizada
                    ]);
                    return $fechaNormalizada;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Último intento con parse genérico
        try {
            $fechaObj = \Carbon\Carbon::parse($fecha);
            $fechaNormalizada = $fechaObj->format('Y-m-d');
            Log::info('[DetalleVehiculo] Fecha normalizada con Carbon::parse', [
                'original' => $fecha,
                'normalizada' => $fechaNormalizada
            ]);
            return $fechaNormalizada;
        } catch (\Exception $e) {
            Log::warning('[DetalleVehiculo] No se pudo normalizar la fecha', [
                'fecha_original' => $fecha,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    /**
     * Enriquecer cita con datos SAP
     */
    protected function enriquecerCitaConDatosSAP(array $cita): array
    {
        $citaEnriquecida = [
            'probable_entrega' => $this->formatearFechaHoraEntregaC4C($cita),
            'asesor' => $cita['telemarketing_advisor'] ?? 'Por asignar',
            'whatsapp' => $cita['client_phone'] ?? '-',
            'correo' => $this->obtenerCorreoDinamico($cita),
            'fecha_factura' => '',
            'hora_factura' => '',
            'rut_pdf' => '',
        ];

        // Enriquecer con datos SAP si están disponibles
        if ($this->datosAsesorSAP) {
            // Solo usar datos SAP si están realmente disponibles y válidos
            if (!empty($this->datosAsesorSAP['nombre_asesor'])) {
                $citaEnriquecida['asesor'] = $this->datosAsesorSAP['nombre_asesor'];
            } else {
                $citaEnriquecida['asesor'] = 'Por asignar';
            }
            
            if (!empty($this->datosAsesorSAP['telefono_asesor'])) {
                $citaEnriquecida['whatsapp'] = $this->datosAsesorSAP['telefono_asesor'];
            } else {
                $citaEnriquecida['whatsapp'] = 'Por asignar';
            }
            
            if (!empty($this->datosAsesorSAP['correo_asesor'])) {
                $citaEnriquecida['correo'] = $this->datosAsesorSAP['correo_asesor'];
            } else {
                $citaEnriquecida['correo'] = 'Por asignar';
            }
            
            // Formatear fecha y hora de entrega SAP solo si son válidas
            if ($this->datosAsesorSAP['tiene_fecha_entrega']) {
                $fechaEntrega = $this->formatearFechaSAP($this->datosAsesorSAP['fecha_entrega']);
                $horaEntrega = $this->datosAsesorSAP['hora_entrega'];
                $citaEnriquecida['probable_entrega'] = $fechaEntrega . ' ' . $horaEntrega;
            } else {
                $citaEnriquecida['probable_entrega'] = 'Por asignar';
            }
            
            // Si hay fecha de factura SAP válida, agregar datos de facturación
            if ($this->datosAsesorSAP['tiene_fecha_factura']) {
                $citaEnriquecida['fecha_factura'] = $this->formatearFechaSAP($this->datosAsesorSAP['fecha_factura']);
                $citaEnriquecida['hora_factura'] = $this->datosAsesorSAP['hora_factura'];
                $citaEnriquecida['rut_pdf'] = $this->datosAsesorSAP['rut_pdf'];
            }
        }

        return $citaEnriquecida;
    }

    /**
     * Obtener correo dinámico del webservice o fallback
     */
    protected function obtenerCorreoDinamico(array $cita): string
    {
        // Intentar obtener correo de diferentes campos del webservice
        $correo = $cita['client_email'] ??
                  $cita['customer_email'] ??
                  $cita['contact_email'] ??
                  null;

        // Si no hay correo en el webservice, usar el fallback
        return $correo ?: 'info@mitsui.com.pe';
    }

    /**
     * Crear cliente SOAP para SAP usando WSDL local
     */
    protected function crearClienteSAP(): ?SoapClient
    {
        if ($this->soapClient !== null) {
            return $this->soapClient;
        }

        try {
            $usuario = config('services.sap_3p.usuario');
            $password = config('services.sap_3p.password');

            if (empty($usuario) || empty($password)) {
                Log::error('[DetalleVehiculo] Configuración SAP incompleta');
                return null;
            }

            // Usar WSDL local como lo hace VehiculoSoapService
            $wsdlLocal = storage_path('wsdl/vehiculos.wsdl');

            if (!file_exists($wsdlLocal)) {
                Log::error('[DetalleVehiculo] WSDL local no encontrado: ' . $wsdlLocal);
                return null;
            }

            $opciones = [
                'trace' => true,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 10,
                'default_socket_timeout' => 10,
                'stream_context' => stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                    'http' => [
                        'timeout' => 10,
                    ],
                ]),
                'login' => $usuario,
                'password' => $password,
            ];

            $this->soapClient = new SoapClient($wsdlLocal, $opciones);
            Log::info('[DetalleVehiculo] Cliente SOAP SAP creado exitosamente usando WSDL local');

            return $this->soapClient;

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al crear cliente SOAP SAP: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cargar datos del vehículo desde SAP
     */
    protected function cargarDatosVehiculoDesdeSAP(string $placa): void
    {
        $this->cargandoDesdeSAP = true;

        try {
            Log::info("[DetalleVehiculo] Iniciando carga de datos SAP para placa: {$placa}");

            // Crear cliente SOAP
            $cliente = $this->crearClienteSAP();
            if (!$cliente) {
                throw new \Exception('No se pudo crear el cliente SOAP');
            }

            // FLUJO CORRECTO:
            // 1. Obtener DNI del usuario logueado
            $documentoCliente = $this->obtenerDocumentoCliente();

            if (!$documentoCliente) {
                throw new \Exception('No se pudo obtener el documento del usuario logueado');
            }

            // 2. Sincronizar TODOS los vehículos del usuario con SAP
            $this->sincronizarVehiculosUsuarioConSAP($cliente, $documentoCliente);

            // 3. Buscar el vehículo específico en la BD (ya actualizada)
            $vehiculoActualizado = $this->buscarVehiculoEnBD($placa);

            if ($vehiculoActualizado) {
                // 4. Cargar datos del vehículo desde BD actualizada
                $this->cargarDatosVehiculo($vehiculoActualizado);

                // 5. Cargar historial de servicios específico de esta placa
                $this->cargarHistorialServiciosSAP($cliente, $placa);
            } else {
                throw new \Exception("El vehículo con placa {$placa} no pertenece al usuario logueado");
            }

            // TERCERO: Cargar datos del asesor si hay servicio en proceso
            $this->cargarDatosAsesorSAP($cliente, $placa);

            // CUARTO: Cargar prepagos disponibles
            $this->cargarPrepagosSAP($cliente, $placa);

            Log::info('[DetalleVehiculo] Datos SAP cargados exitosamente');

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al cargar datos desde SAP: ' . $e->getMessage());

            // Establecer valores de error
            $this->vehiculo = [
                'modelo' => 'Error al consultar SAP',
                'kilometraje' => 'No disponible',
                'placa' => $placa,
                'anio' => 'No disponible',
                'marca' => 'No disponible',
                'color' => 'No disponible',
            ];

            $this->mantenimiento = [
                'ultimo' => 'Error al consultar SAP',
                'fecha' => 'No disponible',
                'vencimiento' => 'No disponible',
                'disponibles' => ['Error al consultar datos'],
            ];
        } finally {
            $this->cargandoDesdeSAP = false;
        }
    }

    /**
     * Sincronizar TODOS los vehículos del usuario logueado con SAP usando Z3PF_GETLISTAVEHICULOS
     */
    protected function sincronizarVehiculosUsuarioConSAP(SoapClient $cliente, string $documentoCliente): void
    {
        try {
            Log::info("[DetalleVehiculo] Sincronizando vehículos del usuario con DNI: {$documentoCliente}");

            // Usar VehiculoSoapService que ya tiene la lógica implementada
            $vehiculoService = app(VehiculoSoapService::class);
            $marcas = ['Z01', 'Z02', 'Z03']; // TOYOTA, LEXUS, HINO

            // Obtener TODOS los vehículos del usuario desde SAP
            $vehiculosSAP = $vehiculoService->getVehiculosDesdeSAP($documentoCliente, $marcas);

            if ($vehiculosSAP->isNotEmpty()) {
                Log::info("[DetalleVehiculo] SAP devolvió {$vehiculosSAP->count()} vehículos para el usuario");

                // Obtener usuario logueado
                $user = Auth::user();

                // Sincronizar cada vehículo en la BD
                foreach ($vehiculosSAP as $vehiculoSAP) {
                    $this->sincronizarVehiculoIndividual($user, $vehiculoSAP);
                }

                Log::info("[DetalleVehiculo] Sincronización completada exitosamente");
            } else {
                Log::warning("[DetalleVehiculo] SAP no devolvió vehículos para el usuario con DNI: {$documentoCliente}");
            }

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al sincronizar vehículos con SAP: " . $e->getMessage());
            // No lanzar excepción para que no interrumpa el flujo
        }
    }

    /**
     * Sincronizar un vehículo individual en la BD
     */
    protected function sincronizarVehiculoIndividual($user, array $vehiculoSAP): void
    {
        try {
            $placa = $vehiculoSAP['numpla'] ?? null;

            if (!$placa) {
                Log::warning("[DetalleVehiculo] Vehículo SAP sin placa, saltando");
                return;
            }

            // Buscar si el vehículo ya existe en la BD
            $vehiculoExistente = Vehicle::where('license_plate', $placa)
                                      ->where('user_id', $user->id)
                                      ->first();

            if ($vehiculoExistente) {
                // Actualizar vehículo existente
                $vehiculoExistente->update([
                    'model' => $vehiculoSAP['modver'] ?? $vehiculoExistente->model,
                    'year' => $vehiculoSAP['aniomod'] ?? $vehiculoExistente->year,
                    'brand_code' => $vehiculoSAP['marca_codigo'] ?? $vehiculoExistente->brand_code,
                    'brand_name' => $this->obtenerNombreMarca($vehiculoSAP['marca_codigo'] ?? ''),
                    'updated_at' => now(),
                ]);

                Log::info("[DetalleVehiculo] Vehículo actualizado en BD: {$placa}");
            } else {
                // Crear nuevo vehículo
                Vehicle::create([
                    'user_id' => $user->id,
                    'vehicle_id' => $vehiculoSAP['vhclie'] ?? 'VH' . uniqid(),
                    'license_plate' => $placa,
                    'model' => $vehiculoSAP['modver'] ?? 'Modelo no especificado',
                    'year' => $vehiculoSAP['aniomod'] ?? date('Y'),
                    'brand_code' => $vehiculoSAP['marca_codigo'] ?? 'Z01',
                    'brand_name' => $this->obtenerNombreMarca($vehiculoSAP['marca_codigo'] ?? 'Z01'),
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("[DetalleVehiculo] Nuevo vehículo creado en BD: {$placa}");
            }

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al sincronizar vehículo individual: " . $e->getMessage());
        }
    }

    /**
     * Buscar vehículo específico en la BD
     */
    protected function buscarVehiculoEnBD(string $placa): ?Vehicle
    {
        try {
            $user = Auth::user();

            $vehiculo = Vehicle::where('license_plate', $placa)
                              ->where('user_id', $user->id)
                              ->first();

            if ($vehiculo) {
                Log::info("[DetalleVehiculo] Vehículo encontrado en BD: {$placa}");
                return $vehiculo;
            } else {
                Log::warning("[DetalleVehiculo] Vehículo no encontrado en BD: {$placa}");
                return null;
            }

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al buscar vehículo en BD: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Buscar datos básicos del vehículo usando Z3PF_GETLISTAVEHICULOS desde SAP
     * Si no se encuentra, usa datos de la base de datos local como fallback
     */
    protected function buscarDatosVehiculoSAP(SoapClient $cliente, string $placa, ?string $documentoCliente = null): ?array
    {
        try {
            Log::info("[DetalleVehiculo] Buscando datos del vehículo con placa: {$placa}");

            // PRIMERO: Intentar obtener datos desde SAP usando Z3PF_GETLISTAVEHICULOS
            if ($documentoCliente) {
                Log::info("[DetalleVehiculo] Consultando SAP Z3PF_GETLISTAVEHICULOS para placa: {$placa} con documento: {$documentoCliente}");

                // Usar el servicio VehiculoSoapService que ya tiene la lógica implementada
                $vehiculoService = app(VehiculoSoapService::class);

                // Buscar en todas las marcas usando el documento del cliente
                $marcas = ['Z01', 'Z02', 'Z03']; // TOYOTA, LEXUS, HINO

                $vehiculosSAP = $vehiculoService->getVehiculosDesdeSAP($documentoCliente, $marcas);
            } else {
                Log::warning("[DetalleVehiculo] No se proporcionó documento del cliente, saltando consulta SAP");
                $vehiculosSAP = collect();
            }

            // Buscar el vehículo específico por placa
            $vehiculoEncontrado = $vehiculosSAP->first(function ($vehiculo) use ($placa) {
                return isset($vehiculo['numpla']) && trim($vehiculo['numpla']) === trim($placa);
            });

            if ($vehiculoEncontrado) {
                Log::info("[DetalleVehiculo] Vehículo encontrado en SAP Z3PF_GETLISTAVEHICULOS");
                return [
                    'vhclie' => $vehiculoEncontrado['vhclie'] ?? '',
                    'numpla' => $vehiculoEncontrado['numpla'] ?? $placa,
                    'aniomod' => $vehiculoEncontrado['aniomod'] ?? '',
                    'modver' => $vehiculoEncontrado['modver'] ?? '',
                    'marca_codigo' => $vehiculoEncontrado['marca_codigo'] ?? 'Z01',
                ];
            }

            Log::info("[DetalleVehiculo] Vehículo no encontrado en SAP, intentando BD local");

            // SEGUNDO: Si no se encuentra en SAP, intentar base de datos local
            $vehiculoLocal = \App\Models\Vehicle::where('license_plate', $placa)->first();

            if ($vehiculoLocal) {
                Log::info("[DetalleVehiculo] Vehículo encontrado en BD local");
                return [
                    'vhclie' => $vehiculoLocal->vehicle_id,
                    'numpla' => $vehiculoLocal->license_plate,
                    'aniomod' => (string) $vehiculoLocal->year,
                    'modver' => $vehiculoLocal->model,
                    'marca_codigo' => $vehiculoLocal->brand_code,
                ];
            }

            // TERCERO: Si no se encuentra en ningún lado, retornar null
            Log::warning("[DetalleVehiculo] Vehículo con placa {$placa} no encontrado en SAP ni en BD local");
            return null;

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al buscar datos del vehículo: " . $e->getMessage());

            // Como fallback, intentar BD local
            try {
                $vehiculoLocal = \App\Models\Vehicle::where('license_plate', $placa)->first();
                if ($vehiculoLocal) {
                    Log::info("[DetalleVehiculo] Usando BD local como fallback después del error SAP");
                    return [
                        'vhclie' => $vehiculoLocal->vehicle_id,
                        'numpla' => $vehiculoLocal->license_plate,
                        'aniomod' => (string) $vehiculoLocal->year,
                        'modver' => $vehiculoLocal->model,
                        'marca_codigo' => $vehiculoLocal->brand_code,
                    ];
                }
            } catch (\Exception $fallbackError) {
                Log::error("[DetalleVehiculo] Error también en fallback BD local: " . $fallbackError->getMessage());
            }

            return null;
        }
    }

    /**
     * Actualizar datos básicos del vehículo con datos de Z3PF_GETLISTAVEHICULOS
     */
    protected function actualizarDatosBasicosVehiculo(array $datosVehiculo, string $placa): void
    {
        try {
            Log::info("[DetalleVehiculo] Actualizando datos básicos del vehículo con datos de SAP");

            $this->vehiculo = [
                'modelo' => $datosVehiculo['modver'] ?? 'No disponible',
                'kilometraje' => 'Consultando SAP...', // Se actualizará con Z3PF_GETLISTASERVICIOS
                'placa' => $placa,
                'anio' => $datosVehiculo['aniomod'] ?? 'No disponible',
                'marca' => $this->obtenerNombreMarca($datosVehiculo['marca_codigo'] ?? ''),
                'color' => 'No disponible', // Este campo no viene en Z3PF_GETLISTAVEHICULOS
                'fuente' => 'SAP',
            ];

            Log::info("[DetalleVehiculo] Datos básicos actualizados:", $this->vehiculo);

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al actualizar datos básicos: " . $e->getMessage());
        }
    }

    /**
     * Cargar historial de servicios desde SAP usando Z3PF_GETLISTASERVICIOS
     */
    protected function cargarHistorialServiciosSAP(SoapClient $cliente, string $placa): void
    {
        try {
            Log::info("[DetalleVehiculo] Consultando historial de servicios SAP para placa: {$placa}");

            // Llamar a Z3PF_GETLISTASERVICIOS con la placa
            $parametros = ['PI_PLACA' => $placa];
            $respuesta = $cliente->Z3PF_GETLISTASERVICIOS($parametros);

            Log::info('[DetalleVehiculo] Respuesta Z3PF_GETLISTASERVICIOS:', (array) $respuesta);

            // Extraer datos desde Z3PF_GETLISTASERVICIOS
            $kilometraje = $respuesta->PE_KILOMETRAJE ?? 0;
            $fechaUltimoServicio = $respuesta->PE_ULT_FEC_SERVICIO ?? '';
            $fechaVencimientoPrepago = $respuesta->PE_ULT_FEC_PREPAGO ?? '';
            $ultimoKm = $respuesta->PE_ULT_KM_ ?? 0;

            // Actualizar SOLO el kilometraje (los demás datos ya vienen de la BD actualizada)
            if ($kilometraje > 0) {
                $this->vehiculo['kilometraje'] = number_format($kilometraje, 0, '.', ',') . ' Km';
                Log::info("[DetalleVehiculo] Kilometraje actualizado desde Z3PF_GETLISTASERVICIOS: {$this->vehiculo['kilometraje']}");
            }

            // Actualizar datos de mantenimiento
            $fechaFormateada = $this->formatearFechaSAP($fechaUltimoServicio);
            $vencimientoFormateado = $this->formatearFechaSAP($fechaVencimientoPrepago);

            $this->mantenimiento = [
                'ultimo' => 'Procesando historial...', // Se actualizará con el historial real
                'fecha' => $fechaFormateada,
                'vencimiento' => $vencimientoFormateado,
                'disponibles' => ['Consultando prepagos...'],
                'ultimo_km' => $ultimoKm > 0 ? number_format($ultimoKm, 0, '.', ',') . ' Km' : 'No disponible',
            ];

            // PROCESAR HISTORIAL DE SERVICIOS (TT_LISSRV) - ESTO ES LO PRINCIPAL
            if (isset($respuesta->TT_LISSRV) && !empty($respuesta->TT_LISSRV)) {
                Log::info('[DetalleVehiculo] Procesando historial de servicios desde TT_LISSRV');
                $this->procesarHistorialServiciosSAP($respuesta->TT_LISSRV);
            } else {
                Log::warning('[DetalleVehiculo] No se encontró historial de servicios en TT_LISSRV');
                $this->historialServicios = collect();
            }

        } catch (SoapFault $e) {
            Log::error('[DetalleVehiculo] Error SOAP al consultar Z3PF_GETLISTASERVICIOS: ' . $e->getMessage());
            $this->historialServicios = collect();
            // No lanzar excepción para que no interrumpa el flujo
        }
    }

    /**
     * Obtener el documento del cliente desde el contexto de usuario autenticado
     */
    protected function obtenerDocumentoCliente(): ?string
    {
        try {
            $user = Auth::user();

            if (!$user) {
                Log::warning("[DetalleVehiculo] No hay usuario autenticado");
                return null;
            }

            // Obtener el documento del usuario autenticado
            $documento = $user->document_number ?? null;

            if ($documento) {
                Log::info("[DetalleVehiculo] Documento del cliente obtenido: {$documento}");
                return $documento;
            } else {
                Log::warning("[DetalleVehiculo] Usuario autenticado no tiene document_number");
                return null;
            }

        } catch (\Exception $e) {
            Log::error("[DetalleVehiculo] Error al obtener documento del cliente: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener el nombre de la marca basado en el código
     */
    protected function obtenerNombreMarca(string $codigoMarca): string
    {
        $marcas = [
            'Z01' => 'TOYOTA',
            'Z02' => 'LEXUS',
            'Z03' => 'HINO',
        ];

        return $marcas[$codigoMarca] ?? 'No disponible';
    }

    /**
     * Cargar datos del asesor desde SAP usando Z3PF_GETDATOSASESORPROCESO
     */
    protected function cargarDatosAsesorSAP(SoapClient $cliente, string $placa): void
    {
        try {
            Log::info("[DetalleVehiculo] Consultando asesor SAP para placa: {$placa}");

            $parametros = ['PI_PLACA' => $placa];
            $respuesta = $cliente->Z3PF_GETDATOSASESORPROCESO($parametros);
            
            // Capturar XML raw para extracción manual de campos
            $xmlResponse = $cliente->__getLastResponse();
            
            Log::info('[DetalleVehiculo] Respuesta SAP asesor:', (array) $respuesta);


            // Extraer todos los datos del asesor SAP
            $nombreAsesor = $respuesta->PE_NOM_ASE ?? '';
            $telefonoAsesor = $respuesta->PE_TEL_ASER ?? '';
            $correoAsesor = $respuesta->PE_COR_ASE ?? '';
            $fechaEntrega = $respuesta->PE_FEC_ENTREGA ?? '';
            $horaEntrega = $respuesta->PE_HOR_ENTREGA ?? '';
            $local = $respuesta->PE_LOCAL ?? '';
            $fechaFactura = $respuesta->PE_FEC_FACTURA ?? '';
            $horaFactura = $respuesta->PE_HOR_FACTURA ?? '';
            $rutPdf = $respuesta->PE_RUT_PDF ?? '';
            $fechaUltServ = $respuesta->PE_FEC_ULT_SERV ?? '';
            
            // Extraer PE_FEC_ULT_SERV del XML si no está en el objeto (problema de parsing PHP)
            if (empty($fechaUltServ) && !empty($xmlResponse)) {
                if (preg_match('/<PE_FEC_ULT_SERV[^>]*>([^<]*)<\/PE_FEC_ULT_SERV>/', $xmlResponse, $matches)) {
                    $fechaUltServ = trim($matches[1]);
                }
            }

            // Validación de fechas SAP
            $validacionFechaUltServ = !empty($fechaUltServ) && $fechaUltServ !== '0000-00-00';
            $validacionFechaFactura = !empty($fechaFactura) && $fechaFactura !== '0000-00-00';
            $validacionFechaEntrega = !empty($fechaEntrega) && $fechaEntrega !== '0000-00-00';



            // Guardar datos del asesor SAP para enriquecer las citas
            $this->datosAsesorSAP = [
                'nombre_asesor' => $nombreAsesor,
                'telefono_asesor' => $telefonoAsesor,
                'correo_asesor' => $correoAsesor,
                'fecha_entrega' => $fechaEntrega,
                'hora_entrega' => $horaEntrega,
                'local' => $local,
                'fecha_factura' => $fechaFactura,
                'hora_factura' => $horaFactura,
                'rut_pdf' => $rutPdf,
                'fecha_ult_serv' => $fechaUltServ,
                'tiene_fecha_entrega' => $validacionFechaEntrega,
                'tiene_fecha_factura' => $validacionFechaFactura,
                'tiene_fecha_ult_serv' => $validacionFechaUltServ,
            ];

            Log::info('[DetalleVehiculo] Datos del asesor SAP guardados:', $this->datosAsesorSAP);

        } catch (SoapFault $e) {
            Log::warning('[DetalleVehiculo] Error SOAP al consultar asesor (no crítico): ' . $e->getMessage());
            // Inicializar con valores vacíos si hay error
            $this->datosAsesorSAP = [
                'nombre_asesor' => '',
                'telefono_asesor' => '',
                'correo_asesor' => '',
                'fecha_entrega' => '',
                'hora_entrega' => '',
                'local' => '',
                'fecha_factura' => '',
                'hora_factura' => '',
                'rut_pdf' => '',
                'fecha_ult_serv' => '',
                'tiene_fecha_entrega' => false,
                'tiene_fecha_factura' => false,
                'tiene_fecha_ult_serv' => false,
            ];
        }
    }

    /**
     * Cargar prepagos disponibles desde SAP usando Z3PF_GETLISTAPREPAGOPEN
     */
    protected function cargarPrepagosSAP(SoapClient $cliente, string $placa): void
    {
        try {
            Log::info("[DetalleVehiculo] Consultando prepagos SAP para placa: {$placa}");

            $parametros = [
                'PI_PLACA' => $placa,
                'PI_PEND' => '', // Campo de uso interno según documentación
            ];
            $respuesta = $cliente->Z3PF_GETLISTAPREPAGOPEN($parametros);

            Log::info('[DetalleVehiculo] Respuesta SAP prepagos:', (array) $respuesta);

            $prepagosDisponibles = [];

            // Procesar lista de prepagos si existe
            if (isset($respuesta->PE_SERV_PREPAGO->item)) {
                // Normalizar a array
                $items = is_array($respuesta->PE_SERV_PREPAGO->item)
                    ? $respuesta->PE_SERV_PREPAGO->item
                    : [$respuesta->PE_SERV_PREPAGO->item];
            
                foreach ($items as $item) {
                    if (isset($item->MAKTX) && !empty($item->MAKTX)) {
                        $prepagosDisponibles[] = $item->MAKTX;
                    }
                }
            }

            // Actualizar datos de mantenimiento con prepagos
            if (!empty($prepagosDisponibles)) {
                $this->mantenimiento['disponibles'] = $prepagosDisponibles;
                Log::info('[DetalleVehiculo] Prepagos encontrados: ' . count($prepagosDisponibles));
            } else {
                $this->mantenimiento['disponibles'] = ['No disponible'];
                Log::info('[DetalleVehiculo] No se encontraron prepagos disponibles');
            }

        } catch (SoapFault $e) {
            Log::warning('[DetalleVehiculo] Error SOAP al consultar prepagos (no crítico): ' . $e->getMessage());
            $this->mantenimiento['disponibles'] = ['Error al consultar prepagos'];
        }
    }

    /**
     * Formatear fecha desde SAP (formato YYYY-MM-DD o 0000-00-00)
     */
    protected function formatearFechaSAP(string $fecha): string
    {
        if (empty($fecha) || $fecha === '0000-00-00') {
            return 'No disponible';
        }

        try {
            $fechaObj = \DateTime::createFromFormat('Y-m-d', $fecha);
            return $fechaObj ? $fechaObj->format('d/m/Y') : 'No disponible';
        } catch (\Exception $e) {
            return 'No disponible';
        }
    }

    /**
     * Formatear hora desde SAP (formato HH:MM:SS o 00:00:00)
     */
    protected function formatearHoraSAP(string $hora): string
    {
        if (empty($hora) || $hora === '00:00:00') {
            return 'No disponible';
        }

        try {
            $horaObj = \DateTime::createFromFormat('H:i:s', $hora);
            return $horaObj ? $horaObj->format('H:i') : 'No disponible';
        } catch (\Exception $e) {
            return 'No disponible';
        }
    }

    /**
     * Procesar historial de servicios desde SAP usando TT_LISSRV
     */
    protected function procesarHistorialServiciosSAP($ttLissrv): void
    {
        try {
            Log::info('[DetalleVehiculo] Procesando historial de servicios SAP');
            Log::info('[DetalleVehiculo] Estructura TT_LISSRV recibida:', (array) $ttLissrv);

            $servicios = [];

            // Verificar si hay items en TT_LISSRV
            if (isset($ttLissrv->item)) {
                $listaServicios = is_array($ttLissrv->item) ? $ttLissrv->item : [$ttLissrv->item];

                Log::info('[DetalleVehiculo] Procesando ' . count($listaServicios) . ' servicios');

                foreach ($listaServicios as $index => $servicio) {
                    Log::info("[DetalleVehiculo] Procesando servicio {$index}:", (array) $servicio);

                    // Extraer todos los campos disponibles del servicio
                    $servicioFormateado = [
                        'fecha' => $this->formatearFechaSAP($servicio->FECSRV ?? ''),
                        'servicio' => $servicio->DESSRV ?? 'Servicio no especificado',
                        'sede' => $servicio->SEDSRV ?? 'No especificado',
                        'asesor' => $servicio->ASESRV ?? 'No especificado',
                        'tipo_pago' => $servicio->TIPPAGSRV ?? 'No especificado',
                        'fecha_raw' => $servicio->FECSRV ?? '',
                    ];

                    $servicios[] = $servicioFormateado;
                    Log::info("[DetalleVehiculo] Servicio {$index} formateado:", $servicioFormateado);
                }
            } else {
                Log::warning('[DetalleVehiculo] No se encontró estructura item en TT_LISSRV');
            }

            if (!empty($servicios)) {
                // Ordenar servicios por fecha (más reciente primero)
                usort($servicios, function($a, $b) {
                    return strcmp($b['fecha_raw'], $a['fecha_raw']);
                });

                $this->historialServicios = collect($servicios);
                Log::info('[DetalleVehiculo] Historial de servicios SAP procesado exitosamente: ' . count($servicios) . ' servicios');

                // Actualizar el último servicio en los datos de mantenimiento
                if (count($servicios) > 0) {
                    $ultimoServicio = $servicios[0];
                    $this->mantenimiento['ultimo'] = $ultimoServicio['servicio'];
                    $this->mantenimiento['fecha'] = $ultimoServicio['fecha'];

                    Log::info('[DetalleVehiculo] Último servicio actualizado: ' . $ultimoServicio['servicio']);
                }
            } else {
                $this->historialServicios = collect();
                Log::info('[DetalleVehiculo] No se encontraron servicios en el historial');
            }

        } catch (\Exception $e) {
            Log::error('[DetalleVehiculo] Error al procesar historial SAP: ' . $e->getMessage());
            $this->historialServicios = collect();
        }
    }

    protected function evaluarEstadoCitas(array $citasDelVehiculo): bool
    {
        foreach ($citasDelVehiculo as $cita) {
            $statusCode = $cita['status']['appointment_code'] ?? '';
            $fechaProgramada = $cita['dates']['scheduled_start_date'] ?? '';

            if (in_array($statusCode, ['1', '2'])) {
                if (empty($fechaProgramada) || strtotime($fechaProgramada) >= strtotime('today')) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Enviar email de notificación de cita cancelada
     */
    protected function enviarEmailCitaCancelada(Appointment $appointment, string $motivoCancelacion = ''): void
    {
        try {
            Log::info('📧 [CitaCancelada] Enviando email de cita cancelada', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_email' => $appointment->customer_email,
                'motivo' => $motivoCancelacion,
            ]);

            // Preparar datos del cliente
            $datosCliente = [
                'nombres' => $appointment->customer_name,
                'apellidos' => $appointment->customer_last_name,
                'email' => $appointment->customer_email,
                'celular' => $appointment->customer_phone,
            ];

            // Preparar datos del vehículo
            $datosVehiculo = [
                'marca' => $this->vehiculo['marca'] ?? 'No especificado',
                'modelo' => $this->vehiculo['modelo'] ?? 'No especificado',
                'placa' => $this->vehiculo['placa'] ?? 'No especificado',
            ];

            // Enviar el correo de cancelación
            Mail::to($appointment->customer_email)
                ->send(new CitaCancelada($appointment, $datosCliente, $datosVehiculo, $motivoCancelacion));

            Log::info('📧 [CitaCancelada] Email de cita cancelada enviado exitosamente', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email,
            ]);

        } catch (\Exception $e) {
            Log::error('📧 [CitaCancelada] Error enviando email de cita cancelada', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // No lanzar excepción para no interrumpir el proceso de cancelación
            // Solo registrar el error
        }
    }

}
