<?php

namespace App\Filament\Pages;

use App\Jobs\EnviarCitaC4CJob;
use App\Jobs\ProcessAppointmentAfterCreationJob;
use App\Mail\SolicitudInformacionPopup;
use App\Mail\CitaCreada;
use App\Mail\CitaEditada;
use App\Mail\CitaCancelada;
use App\Models\AdditionalService;
use App\Models\Appointment;
use App\Models\AppointmentAdditionalService;
use App\Models\Bloqueo;
use App\Models\Campana;
use App\Models\Local;
use App\Models\MaintenanceType;
use App\Models\ModelMaintenance;
use App\Models\Vehicle;
use App\Models\VehiculoExpress;
use App\Services\VehiculoSoapService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\C4C\AvailabilityService;
use App\Jobs\SyncAppointmentToC4CJob;
use App\Services\C4C\ProductService;
use App\Services\C4C\VehicleService;
use App\Models\CenterOrganizationMapping;
use App\Jobs\CreateOfferJob;

class AgendarCita extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Agendar Cita';

    protected static ?string $navigationGroup = '📅 Citas & Servicios';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Agendar Cita';

    protected static string $view = 'filament.pages.agendar-cita';

    // Ocultar de la navegación principal ya que se accederá desde la página de vehículos
    protected static bool $shouldRegisterNavigation = false;

    // Datos del vehículo seleccionado (se pasarán como parámetros)
    public array $vehiculo = [
        'id' => '',
        'placa' => '',
        'modelo' => '',
        'anio' => '',
        'marca' => '',
        'nummot' => '', // Campo NUMMOT de SAP para código de motor
    ];

    // Datos del formulario (se cargarán automáticamente desde el usuario autenticado)
    public string $nombreCliente = '';

    public string $emailCliente = '';

    public string $apellidoCliente = '';

    public string $celularCliente = '';

    // Datos de la cita
    public string $fechaSeleccionada = '';

    public string $horaSeleccionada = '';

    public string $localSeleccionado = '';

    public array $serviciosSeleccionados = [];

    public string $tipoMantenimiento = '';

    public string $modalidadServicio = '';

    // Datos del calendario
    public int $mesActual;

    public int $anoActual;

    public array $diasCalendario = [];

    public array $horariosDisponibles = [];

    // Debug info para mostrar en el frontend
    public array $debugInfo = [
        'status' => 'Pendiente',
        'details' => 'Selecciona una fecha para ver detalles...',
        'total_slots' => 0,
        'validation_method' => 'N/A'
    ];

    // Servicios adicionales
    public array $serviciosAdicionales = [];

    // Comentarios
    public string $comentarios = '';

    // Servicios extras elegidos (campo oculto para autocompletado)
    public string $serviciosExtrasElegidos = '';

    // Pasos del formulario
    public int $pasoActual = 1;

    public int $totalPasos = 3;

    public bool $citaAgendada = false;

    // Estados del job de C4C
    public ?string $citaJobId = null;

    public string $citaStatus = 'idle'; // idle, processing, completed, failed

    public int $citaProgress = 0;

    public string $citaMessage = '';

    public ?string $appointmentNumber = null;

    // Modales de pop-ups
    public bool $mostrarModalPopups = false;

    public bool $mostrarModalResumenPopups = false;

    public array $popupsSeleccionados = [];

    public array $popupsDisponibles = [];

    // Locales disponibles
    public array $locales = [];

    // Horarios disponibles
    public array $horarios = [];

    // Servicios adicionales disponibles
    public array $opcionesServiciosAdicionales = [];

    // Campañas disponibles
    public array $campanasDisponibles = [];

    // Campaña seleccionada (solo una)
    public $campanaSeleccionada = null;

    // Modalidades disponibles
    public array $modalidadesDisponibles = [];

    // Nuevas propiedades para los maestros
    public array $tiposMantenimientoDisponibles = [];

    // Nueva propiedad para el tipo de vehículo ExpressAdd commentMore actions
    public ?string $tipoExpress = null;

    // Propiedades para modo edición
    public bool $editMode = false;
    public ?string $originalCitaId = null;
    public ?string $originalUuid = null;
    public ?string $originalCenterId = null;
    public ?string $originalDate = null;
    public ?string $originalTime = null;
    public ?string $originalServicio = null;
    public ?string $originalSede = null;

    public array $serviciosAdicionalesDisponibles = [];

    public string $servicioAdicionalSeleccionado = '';

    // NUEVAS PROPIEDADES PARA INTEGRACIÓN COMPLETA C4C
    public ?string $paqueteId = null;
    public array $datosVehiculo = [];
    public bool $vehiculoValidado = false;
    public string $errorValidacionVehiculo = '';

    public bool $consultandoDisponibilidad = false;
    public string $errorDisponibilidad = '';
    public bool $usarHorariosC4C = true;
    public array $slotsC4C = [];
    public array $slotsCompletos = []; // ✅ SMART: Slots con información de disponibilidad
    public string $estadoConexionC4C = 'unknown';

    // Propiedades para edición de datos del cliente
    public bool $editandoDatos = false;
    public string $nombreClienteOriginal = '';
    public string $apellidoClienteOriginal = '';
    public string $emailClienteOriginal = '';
    public string $celularClienteOriginal = '';

    // Propiedades para validación de intervalos de reserva
    public ?int $minReservationTime = null;
    public ?string $minTimeUnit = null;
    public ?int $maxReservationTime = null;
    public ?string $maxTimeUnit = null;




    public function verificarConexionC4C(): void
    {
        try {
            $availabilityService = app(AvailabilityService::class);
            $healthCheck = $availabilityService->healthCheck();

            if ($healthCheck['success']) {
                $this->estadoConexionC4C = 'connected';
                $this->usarHorariosC4C = true;
                Log::info('✅ Conexión C4C establecida');
            } else {
                $this->estadoConexionC4C = 'error';
                $this->usarHorariosC4C = false;
                $this->errorDisponibilidad = 'Error de conexión con C4C';

                // AGREGAR ESTA LÍNEA TEMPORAL PARA DEBUG:
                Log::error('💥 Error específico C4C', ['error_details' => $healthCheck]);

                Log::warning('⚠️ Error de conexión C4C');
            }
        } catch (\Exception $e) {
            $this->estadoConexionC4C = 'error';
            $this->usarHorariosC4C = false;
            $this->errorDisponibilidad = 'Error interno: ' . $e->getMessage();

            // AGREGAR ESTA LÍNEA TEMPORAL PARA DEBUG:
            Log::error('💥 Excepción específica C4C', ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            Log::error('💥 Excepción verificando conexión C4C');
        }
    }






    public function toggleHorariosC4C(): void
    {
        $this->usarHorariosC4C = !$this->usarHorariosC4C;

        Log::info('🔄 Toggle horarios C4C', [
            'usar_c4c' => $this->usarHorariosC4C
        ]);

        if (!empty($this->fechaSeleccionada)) {
            $this->cargarHorariosDisponibles();
        }
    }


    protected function mapearCodigoLocalAC4C(string $codigoLocal): string
    {
        // Los datos en C4C usan directamente los códigos M, no L
        // Solo L013 (Lexus) sigue usando L013
        $mapping = [
            'M013' => 'M013', // Molina - usar código original
            'M023' => 'M023', // Canada - usar código original
            'M303' => 'M303', // Miraflores - usar código original
            'M313' => 'M313', // Arequipa - usar código original
            'M033' => 'M033', // Hino - usar código original
            'L013' => 'L013', // Lexus - mantener L013
        ];

        return $mapping[$codigoLocal] ?? $codigoLocal;
    }

    protected function cargarHorariosDesdeC4C(string $fechaStr, string $codigoLocal): void
    {
        $this->consultandoDisponibilidad = true;
        $this->errorDisponibilidad = '';

        try {
            // ✅ Asegurar formato limpio de fecha (Y-m-d)
            $fechaFormateada = $fechaStr instanceof \Carbon\Carbon
                ? $fechaStr->format('Y-m-d')
                : (new \Carbon\Carbon($fechaStr))->format('Y-m-d');

            $availabilityService = app(AvailabilityService::class);
            $c4cCenterId = $this->mapearCodigoLocalAC4C($codigoLocal);

            Log::info('🔍 [AgendarCita] Consultando disponibilidad C4C', [
                'codigo_local' => $codigoLocal,
                'c4c_center_id' => $c4cCenterId,
                'fecha' => $fechaFormateada
            ]);

            $result = $availabilityService->getAvailableSlotsWithCache($c4cCenterId, $fechaFormateada, 300);

            if ($result['success']) {
                $this->slotsC4C = $result['slots'];
                $slotsDisponibles = $this->filtrarSlotsOcupados($result['slots'], $fechaFormateada, $codigoLocal);
                $this->horariosDisponibles = $this->convertirSlotsAHorarios($slotsDisponibles);

                // ✅ CACHÉ: Optimizar consulta de bloqueos
                $bloqueosCacheKey = "bloqueos:{$codigoLocal}:{$fechaFormateada}";
                $bloqueos = Cache::remember($bloqueosCacheKey, 300, function() use ($codigoLocal, $fechaFormateada) {
                    return \App\Models\Bloqueo::where('premises', $codigoLocal)
                        ->whereDate('start_date', '<=', $fechaFormateada)
                        ->whereDate('end_date', '>=', $fechaFormateada)
                        ->get()
                        ->map(function ($bloqueo) {
                            return [
                                'inicio' => Carbon::createFromFormat('Y-m-d H:i:s', $bloqueo->start_date)->setTimeFromTimeString($bloqueo->start_time),
                                'fin'    => Carbon::createFromFormat('Y-m-d H:i:s', $bloqueo->end_date)->setTimeFromTimeString($bloqueo->end_time),
                            ];
                        });
                });

                if ($fechaStr instanceof \Carbon\Carbon) {
                    $fechaFormateada = $fechaStr->format('Y-m-d');
                } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $fechaStr)) {
                    $fechaFormateada = $fechaStr;
                } else {
                    $fechaFormateada = Carbon::parse($fechaStr)->format('Y-m-d');
                }

                $this->horariosDisponibles = collect($this->horariosDisponibles)->filter(function ($hora) use ($bloqueos, $fechaFormateada) {
                    try {
                        $horaCompleta = Carbon::parse("{$fechaFormateada} {$hora}");

                        foreach ($bloqueos as $bloqueo) {
                            if ($horaCompleta->gte($bloqueo['inicio']) && $horaCompleta->lt($bloqueo['fin'])) {
                                return false;
                            }
                        }
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                })->values()->toArray();
            } else {
                $this->errorDisponibilidad = 'Error C4C. No se pudo obtener disponibilidad.';
                $this->horariosDisponibles = [];
            }
        } catch (\Exception $e) {
            $this->errorDisponibilidad = 'Error consultando C4C.';
            $this->horariosDisponibles = [];
        } finally {
            $this->consultandoDisponibilidad = false;
        }
    }


    protected function filtrarSlotsOcupados(array $slots, string $fechaStr, string $codigoLocal): array
    {
        // ✅ SMART: NO filtrar slots, solo marcar disponibilidad adicional desde BD local
        $localId = $this->locales[$codigoLocal]['id'] ?? null;
        if (!$localId) return $slots;

        $citasAgendadas = Appointment::where('premise_id', $localId)
            ->where('appointment_date', $fechaStr)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->pluck('appointment_time')
            ->map(function ($time) {
                return Carbon::parse($time)->format('H:i');
            })
            ->toArray();

        // YAGNI: Mantener TODOS los slots pero actualizar disponibilidad si hay citas locales adicionales
        return array_map(function ($slot) use ($citasAgendadas) {
            $tieneCtaLocal = in_array($slot['start_time_formatted'], $citasAgendadas);
            
            // Si el slot ya está marcado como no disponible por C4C, mantenerlo
            // Si hay cita local adicional, marcarlo como no disponible
            if ($tieneCtaLocal && ($slot['is_available'] ?? true)) {
                $slot['is_available'] = false;
                $slot['local_appointment_conflict'] = true;
            }
            
            return $slot;
        }, $slots);
    }

    protected function convertirSlotsAHorarios(array $slots): array
    {
        // ✅ KISS: Guardar slots completos para uso posterior, pero devolver estructura compatible
        $this->slotsCompletos = $slots; // Guardar slots completos con is_available
        
        // YAGNI: Por ahora mantener compatibilidad con código existente
        $horarios = [];
        foreach ($slots as $slot) {
            $horarios[] = date('H:i', strtotime($slot['start_time_formatted']));
        }
        
        // Eliminar horarios duplicados
        $horarios = array_unique($horarios);
        sort($horarios);
        
        return array_values($horarios);
    }

    /**
     * ✅ FIX: Limpiar estado del componente
     */
    protected function limpiarEstadoHorarios(): void
    {
        $this->horariosDisponibles = [];
        $this->slotsCompletos = [];
        $this->slotsC4C = [];
        $this->horaSeleccionada = '';
    }

    /**
     * ✅ SMART: Convertir horarios simples a estructura completa para la vista
     */
    protected function convertirHorariosParaVista(): void
    {
        // ✅ FIX: Manejar caso cuando slotsCompletos está vacío (datos del cache sin slots)
        if (empty($this->horariosDisponibles)) {
            Log::warning('⚠️ [VISTA] No hay horarios disponibles para convertir');
            return;
        }
        
        if (empty($this->slotsCompletos)) {
            Log::warning('⚠️ [VISTA] slotsCompletos está vacío, aplicando estructura básica');
            $this->aplicarEstructuraBasica();
            return;
        }

        $horariosConDisponibilidad = [];
        
        foreach ($this->horariosDisponibles as $hora) {
            // Buscar el slot correspondiente en slotsCompletos
            $slotCorrespondiente = null;
            foreach ($this->slotsCompletos as $slot) {
                $slotTime = date('H:i', strtotime($slot['start_time_formatted']));
                if ($slotTime === $hora) {
                    $slotCorrespondiente = $slot;
                    break;
                }
            }

            if ($slotCorrespondiente) {
                $horariosConDisponibilidad[] = [
                    'time' => $hora,
                    'is_available' => $slotCorrespondiente['is_available'] ?? true,
                    'capacity_info' => $slotCorrespondiente['capacity_validation'] ?? [],
                    'local_conflict' => $slotCorrespondiente['local_appointment_conflict'] ?? false
                ];
            } else {
                // Fallback para horarios sin slot correspondiente
                $horariosConDisponibilidad[] = [
                    'time' => $hora,
                    'is_available' => true,
                    'capacity_info' => [],
                    'local_conflict' => false
                ];
            }
        }

        Log::info('✅ [VISTA] Horarios convertidos para vista', [
            'horarios_simples' => count($this->horariosDisponibles),
            'horarios_con_disponibilidad' => count($horariosConDisponibilidad),
            'disponibles' => count(array_filter($horariosConDisponibilidad, fn($h) => $h['is_available'])),
            'no_disponibles' => count(array_filter($horariosConDisponibilidad, fn($h) => !$h['is_available']))
        ]);

        $this->horariosDisponibles = $horariosConDisponibilidad;
    }

    /**
     * ✅ FIX: Aplicar estructura básica cuando no hay slotsCompletos
     */
    protected function aplicarEstructuraBasica(): void
    {
        $horariosConEstructura = [];
        
        foreach ($this->horariosDisponibles as $horario) {
            // ✅ FIX: Detectar si ya tiene estructura o es string simple
            if (is_array($horario)) {
                // Ya tiene estructura, mantenerla
                $horariosConEstructura[] = $horario;
            } else {
                // Es string simple, aplicar estructura básica
                $horariosConEstructura[] = [
                    'time' => $horario,
                    'is_available' => true, // Asumir disponible por defecto
                    'capacity_info' => [],
                    'local_conflict' => false,
                    'origen' => 'estructura_basica'
                ];
            }
        }
        
        Log::info('✅ [VISTA] Estructura básica aplicada', [
            'horarios_procesados' => count($horariosConEstructura),
            'horarios_originales' => count($this->horariosDisponibles)
        ]);
        
        $this->horariosDisponibles = $horariosConEstructura;
    }

    /**
     * Carga los pop-ups disponibles desde la base de datos
     */
    protected function cargarPopups(): void
    {
        try {
            Log::info('[AgendarCita] Iniciando carga de pop-ups');

            // Obtener los pop-ups activos
            $popups = \App\Models\PopUp::where('is_active', true)->get();

            Log::info('[AgendarCita] Pop-ups activos encontrados: ' . $popups->count());

            if ($popups->isNotEmpty()) {
                $this->popupsDisponibles = [];

                foreach ($popups as $popup) {
                    $imagenUrl = $popup->image_path;

                    // Si la imagen es una ruta relativa, convertirla a URL completa
                    if (! filter_var($imagenUrl, FILTER_VALIDATE_URL)) {
                        $imagenUrl = asset('storage/' . $imagenUrl);
                    }

                    $this->popupsDisponibles[] = [
                        'id' => $popup->id,
                        'nombre' => $popup->name,
                        'imagen' => $imagenUrl,
                        'url_wp' => $popup->url_wp,
                    ];

                    Log::info("[AgendarCita] Pop-up cargado: ID {$popup->id}, Nombre: {$popup->name}");
                }

                Log::info('[AgendarCita] Total pop-ups disponibles: ' . count($this->popupsDisponibles));
            } else {
                // Si no hay pop-ups en la base de datos, crear algunos pop-ups de ejemplo
                $this->popupsDisponibles = [];
                Log::info('[AgendarCita] No hay pop-ups activos en la base de datos');
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar pop-ups: ' . $e->getMessage());
            $this->popupsDisponibles = [];
        }
    }

    /**
     * Cargar la configuración de intervalos de reserva desde la base de datos
     */
    protected function cargarConfiguracionIntervalos(): void
    {
        try {
            Log::info('[AgendarCita] Cargando configuración de intervalos de reserva');

            // Si hay un local seleccionado, cargar su configuración específica
            if (!empty($this->localSeleccionado) && isset($this->locales[$this->localSeleccionado]['id'])) {
                $localId = $this->locales[$this->localSeleccionado]['id'];
                $interval = \App\Models\Interval::where('local_id', $localId)->first();
                
                if ($interval) {
                    $this->minReservationTime = $interval->min_reservation_time;
                    $this->minTimeUnit = $interval->min_time_unit;
                    $this->maxReservationTime = $interval->max_reservation_time;
                    $this->maxTimeUnit = $interval->max_time_unit;

                    Log::info('[AgendarCita] Configuración de intervalos cargada para local específico:', [
                        'local_id' => $localId,
                        'local_codigo' => $this->localSeleccionado,
                        'min_time' => $this->minReservationTime,
                        'min_unit' => $this->minTimeUnit,
                        'max_time' => $this->maxReservationTime,
                        'max_unit' => $this->maxTimeUnit,
                    ]);
                    return;
                }
            }

            // Fallback: Obtener la configuración general (primer intervalo disponible)
            $interval = \App\Models\Interval::query()->first();
            
            if ($interval) {
                $this->minReservationTime = $interval->min_reservation_time;
                $this->minTimeUnit = $interval->min_time_unit;
                $this->maxReservationTime = $interval->max_reservation_time;
                $this->maxTimeUnit = $interval->max_time_unit;

                Log::info('[AgendarCita] Configuración de intervalos cargada (fallback general):', [
                    'min_time' => $this->minReservationTime,
                    'min_unit' => $this->minTimeUnit,
                    'max_time' => $this->maxReservationTime,
                    'max_unit' => $this->maxTimeUnit,
                ]);
            } else {
                // Valores por defecto si no hay configuración
                $this->minReservationTime = 1;
                $this->minTimeUnit = 'days';
                $this->maxReservationTime = 30;
                $this->maxTimeUnit = 'days';

                Log::info('[AgendarCita] No se encontró configuración de intervalos, usando valores por defecto');
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar configuración de intervalos: ' . $e->getMessage());
            
            // Valores por defecto en caso de error
            $this->minReservationTime = 1;
            $this->minTimeUnit = 'days';
            $this->maxReservationTime = 30;
            $this->maxTimeUnit = 'days';
        }
    }

    /**
     * Cargar datos del cliente autenticado automáticamente
     */
    protected function cargarDatosCliente(): void
    {
        try {
            $user = Auth::user();

            if ($user) {
                // Dividir el nombre completo en nombres y apellidos
                $nombreCompleto = $user->name ?? '';
                $partesNombre = explode(' ', trim($nombreCompleto));

                // Si hay al menos una palabra, la primera va a nombres
                if (count($partesNombre) >= 1) {
                    $this->nombreCliente = $partesNombre[0];
                }

                // Si hay más de una palabra, el resto va a apellidos
                if (count($partesNombre) > 1) {
                    $this->apellidoCliente = implode(' ', array_slice($partesNombre, 1));
                }

                // Asignar email y teléfono
                $this->emailCliente = $user->email ?? '';
                $this->celularCliente = $user->phone ?? '';

                Log::info('[AgendarCita] Datos del cliente cargados automáticamente:', [
                    'nombre' => $this->nombreCliente,
                    'apellido' => $this->apellidoCliente,
                    'email' => $this->emailCliente,
                    'celular' => $this->celularCliente,
                    'user_id' => $user->id,
                    'document_type' => $user->document_type,
                    'document_number' => $user->document_number,
                ]);
            } else {
                Log::warning('[AgendarCita] No hay usuario autenticado. Manteniendo campos vacíos.');
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar datos del cliente: ' . $e->getMessage());
            // Los campos quedarán vacíos en caso de error
        }
    }

    public function mount($vehiculoId = null): void
    {
        Log::info('[AgendarCita] === MOUNT EJECUTÁNDOSE ===');

        // Registrar todos los parámetros recibidos para depuración
        Log::info('[AgendarCita] Parámetros recibidos en mount:', [
            'vehiculoId' => $vehiculoId,
            'request_all' => request()->all(),
            'query_string' => request()->getQueryString(),
        ]);

        // DETECTAR MODO EDICIÓN
        $this->detectarModoEdicion();

        $this->verificarConexionC4C();

        // CARGAR DATOS DEL CLIENTE AUTENTICADO AUTOMÁTICAMENTE
        $this->cargarDatosCliente();

        // Intentar obtener el ID del vehículo de diferentes fuentes
        if (empty($vehiculoId) && request()->has('vehiculoId')) {
            $vehiculoId = request()->input('vehiculoId');
            Log::info("[AgendarCita] Obteniendo vehiculoId desde request()->input(): {$vehiculoId}");
        }

        // Cargar datos del vehículo basados en el ID recibido
        if ($vehiculoId) {
            // Limpiar el ID del vehículo (eliminar espacios)
            $vehiculoId = trim(str_replace(' ', '', $vehiculoId));
            Log::info("[AgendarCita] Cargando datos para vehículo ID (limpio): {$vehiculoId}");

            // PRIORIZAR SAP: Intentar cargar desde SAP primero para obtener el campo NUMMOT
            Log::info('[AgendarCita] 🚀 Intentando cargar vehículo desde SAP primero para obtener NUMMOT...');
            
            try {
                $service = app(VehiculoSoapService::class);

                // Obtener documento del usuario autenticado
                $user = Auth::user();
                $documentoCliente = $user ? $user->document_number : null;

                if (! $documentoCliente) {
                    Log::warning('[AgendarCita] No se encontró documento del usuario autenticado, usando documento por defecto');
                    $documentoCliente = '20605414410'; // Fallback
                }

                Log::info("[AgendarCita] Consultando vehículos SAP con documento: {$documentoCliente}");
                $marcas = ['Z01', 'Z02', 'Z03']; // Todas las marcas disponibles

                // Obtener todos los vehículos del cliente desde SAP
                $vehiculos = $service->getVehiculosCliente($documentoCliente, $marcas);
                Log::info("[AgendarCita] Total de vehículos obtenidos del servicio SAP: {$vehiculos->count()}");

                // Log detallado de todos los vehículos obtenidos de SAP
                Log::info("[AgendarCita] 🔍 Vehículos obtenidos de SAP para búsqueda:");
                foreach ($vehiculos as $index => $veh) {
                    Log::info("[AgendarCita] - Vehículo #{$index}: ID={$veh['vhclie']}, Placa={$veh['numpla']}, NUMMOT={$veh['nummot']}");
                }
                
                // Buscar el vehículo por ID (puede ser placa o vhclie)
                $vehiculoEncontradoSoap = $vehiculos->first(function ($vehiculo) use ($vehiculoId) {
                    $coincidePlaca = strtoupper($vehiculo['numpla']) == strtoupper($vehiculoId);
                    $coincideId = $vehiculo['vhclie'] == $vehiculoId;
                    
                    Log::info("[AgendarCita] 🔍 Comparando vehículo: ID={$vehiculo['vhclie']}, Placa={$vehiculo['numpla']}, NUMMOT={$vehiculo['nummot']}");
                    Log::info("[AgendarCita] - Buscando: '{$vehiculoId}'");
                    Log::info("[AgendarCita] - Coincide placa: " . ($coincidePlaca ? 'SÍ' : 'NO'));
                    Log::info("[AgendarCita] - Coincide ID: " . ($coincideId ? 'SÍ' : 'NO'));
                    
                    return $coincidePlaca || $coincideId;
                });

                if ($vehiculoEncontradoSoap) {
                    // Usar NUMMOT real de SAP si está disponible, sino usar extracción temporal del MODVER
                    $nummotFinal = '';
                    
                    if (!empty($vehiculoEncontradoSoap['nummot'])) {
                        // USAR NUMMOT REAL DE SAP (PREFERIDO)
                        $nummotFinal = $vehiculoEncontradoSoap['nummot'];
                        Log::info("[AgendarCita] ✅ Usando NUMMOT real de SAP: '{$nummotFinal}'");
                    } else {
                        // FALLBACK: Extraer código de motor del campo MODVER solo si NUMMOT está vacío
                        $nummotFinal = $this->extraerCodigoMotorDeModver($vehiculoEncontradoSoap['modver'] ?? '');
                        Log::warning("[AgendarCita] ⚠️ NUMMOT vacío en SAP, usando extracción temporal del MODVER '{$vehiculoEncontradoSoap['modver']}': '{$nummotFinal}'");
                    }
                    
                    // Obtener tipo_valor_trabajo desde BD local o C4C
                    $tipoValorTrabajo = null;
                    try {
                        // Primero intentar desde BD local
                        $vehiculoLocal = Vehicle::where('license_plate', $vehiculoEncontradoSoap['numpla'])->first();
                        if ($vehiculoLocal && !empty($vehiculoLocal->tipo_valor_trabajo)) {
                            $tipoValorTrabajo = $vehiculoLocal->tipo_valor_trabajo;
                            Log::info("[AgendarCita] Tipo valor trabajo obtenido desde BD local: {$tipoValorTrabajo}");
                        } else {
                            // Si no está en BD local, consultar C4C
                            $vehicleService = app(VehicleService::class);
                            $tipoValorTrabajo = $vehicleService->obtenerTipoValorTrabajoPorPlaca($vehiculoEncontradoSoap['numpla']);
                            Log::info("[AgendarCita] Tipo valor trabajo obtenido desde C4C: " . ($tipoValorTrabajo ?? 'null'));
                        }
                    } catch (\Exception $e) {
                        Log::warning("[AgendarCita] Error obteniendo tipo_valor_trabajo: " . $e->getMessage());
                    }

                    // Si encontramos el vehículo en SAP, usamos sus datos (INCLUYE NUMMOT y tipo_valor_trabajo)
                    $this->vehiculo = [
                        'id' => $vehiculoEncontradoSoap['vhclie'],
                        'placa' => $vehiculoEncontradoSoap['numpla'],
                        'modelo' => $vehiculoEncontradoSoap['modver'],
                        'anio' => $vehiculoEncontradoSoap['aniomod'],
                        'marca' => isset($vehiculoEncontradoSoap['marca_codigo']) ?
                            ($vehiculoEncontradoSoap['marca_codigo'] == 'Z01' ? 'TOYOTA' : ($vehiculoEncontradoSoap['marca_codigo'] == 'Z02' ? 'LEXUS' : 'HINO')) : 'TOYOTA',
                        'tipo_valor_trabajo' => $tipoValorTrabajo,
                        'nummot' => $nummotFinal, // Campo NUMMOT real de SAP o extraído del MODVER
                    ];
                    
                    Log::info('[AgendarCita] ✅ Vehículo encontrado en SAP con NUMMOT:', $this->vehiculo);
                } else {
                    Log::warning("[AgendarCita] Vehículo no encontrado en SAP. Intentando base de datos local...");
                    
                    // FALLBACK: Si no se encuentra en SAP, buscar en base de datos local
                    $vehiculoEncontrado = Vehicle::where('license_plate', $vehiculoId)
                        ->orWhere('vehicle_id', $vehiculoId)
                        ->first();

                    if ($vehiculoEncontrado) {
                        // Si encontramos el vehículo en la base de datos, usamos sus datos
                        $this->vehiculo = [
                            'id' => $vehiculoEncontrado->vehicle_id,
                            'placa' => $vehiculoEncontrado->license_plate,
                            'modelo' => $vehiculoEncontrado->model,
                            'anio' => $vehiculoEncontrado->year,
                            'marca' => $vehiculoEncontrado->brand_name,
                            'brand_name' => $vehiculoEncontrado->brand_name,
                            'brand_code' => $vehiculoEncontrado->brand_code,
                            'tipo_valor_trabajo' => $vehiculoEncontrado->tipo_valor_trabajo,
                            'nummot' => $vehiculoEncontrado->engine_number ?? '', // Usar engine_number de BD si existe
                        ];

                        Log::info('[AgendarCita] 🔍 Vehículo encontrado en la BASE DE DATOS LOCAL (fallback):', $this->vehiculo);
                        Log::warning('[AgendarCita] ⚠️ El vehículo viene de BD local, no de SAP. Campo NUMMOT puede estar vacío.');
                    } else {
                        Log::warning("[AgendarCita] Vehículo no encontrado ni en SAP ni en BD local. Manteniendo valores predeterminados.");
                    }
                }
            } catch (\Exception $e) {
                Log::error('[AgendarCita] Error al cargar datos del vehículo desde SAP: ' . $e->getMessage());
                
                // FALLBACK: En caso de error con SAP, intentar base de datos local
                $vehiculoEncontrado = Vehicle::where('license_plate', $vehiculoId)
                    ->orWhere('vehicle_id', $vehiculoId)
                    ->first();

                if ($vehiculoEncontrado) {
                    // Si encontramos el vehículo en la base de datos, usamos sus datos
                    $this->vehiculo = [
                        'id' => $vehiculoEncontrado->vehicle_id,
                        'placa' => $vehiculoEncontrado->license_plate,
                        'modelo' => $vehiculoEncontrado->model,
                        'anio' => $vehiculoEncontrado->year,
                        'marca' => $vehiculoEncontrado->brand_name,
                        'brand_name' => $vehiculoEncontrado->brand_name,
                        'brand_code' => $vehiculoEncontrado->brand_code,
                        'tipo_valor_trabajo' => $vehiculoEncontrado->tipo_valor_trabajo,
                        'nummot' => $vehiculoEncontrado->engine_number ?? '', // Usar engine_number de BD si existe
                    ];

                    Log::info('[AgendarCita] 🔍 Vehículo encontrado en la BASE DE DATOS LOCAL (fallback por error SAP):', $this->vehiculo);
                    Log::warning('[AgendarCita] ⚠️ El vehículo viene de BD local por error en SAP. Campo NUMMOT puede estar vacío.');
                } else {
                    Log::warning("[AgendarCita] Vehículo no encontrado ni en SAP ni en BD local. Manteniendo valores predeterminados.");
                }
            }
        } else {
            // Si no se proporcionó un ID de vehículo, mantener los valores predeterminados
            Log::warning('[AgendarCita] No se proporcionó ID de vehículo. Manteniendo valores predeterminados.');
        }

        // Cargar los locales desde la base de datos
        $this->cargarLocales();

        // Cargar los servicios adicionales desde la base de datos
        $this->cargarServiciosAdicionales();

        // Cargar las modalidades disponibles
        $this->cargarModalidadesDisponibles();

        // Cargar los tipos de mantenimiento disponibles
        $this->cargarTiposMantenimiento();

        // Cargar los servicios adicionales disponibles
        $this->cargarServiciosAdicionalesDisponibles();

        // Cargar los pop-ups disponibles
        $this->cargarPopups();

        // Cargar la configuración de intervalos de reserva
        $this->cargarConfiguracionIntervalos();

        // Inicializar el calendario con el mes y año actual
        $fechaActual = Carbon::now();
        $this->mesActual = $fechaActual->month;
        $this->anoActual = $fechaActual->year;

        // Generar el calendario para el mes actual
        $this->generarCalendario();

        // Verificar el estado final de la variable vehiculo
        Log::info('[AgendarCita] Estado final de la variable vehiculo:', $this->vehiculo ?? ['vehiculo' => 'null']);

        // Obtener paquete ID si ya se seleccionó tipo de mantenimiento, servicios o campañas
        if ($this->tipoMantenimiento || !empty($this->serviciosAdicionales) || !empty($this->campanaSeleccionada)) {
            $this->obtenerPaqueteId();
        }

        // Validar vehículo si se pasó vehiculoId
        if ($vehiculoId) {
            $this->validarVehiculo($vehiculoId);
        }
        
        // Recargar servicios filtrados por marca después de cargar el vehículo
        $this->recargarServiciosPorMarca();
    }

    /**
     * Recargar todos los servicios filtrados por marca del vehículo
     */
    protected function recargarServiciosPorMarca(): void
    {
        try {
            if (!empty($this->vehiculo['marca'])) {
                $marcaVehiculo = strtoupper(trim($this->vehiculo['marca']));
                Log::info("[AgendarCita] Recargando servicios filtrados por marca: {$marcaVehiculo}");
                
                // Recargar tipos de mantenimiento
                $this->cargarTiposMantenimiento();
                
                // Recargar servicios adicionales
                $this->cargarServiciosAdicionales();
                $this->cargarServiciosAdicionalesDisponibles();
                
                // Recargar campañas
                $this->cargarCampanas();
                
                Log::info("[AgendarCita] Servicios recargados por marca {$marcaVehiculo}:");
                Log::info("- Tipos de mantenimiento: " . count($this->tiposMantenimientoDisponibles));
                Log::info("- Servicios adicionales: " . count($this->serviciosAdicionalesDisponibles));
                Log::info("- Campañas disponibles: " . count($this->campanasDisponibles));
            } else {
                Log::info("[AgendarCita] No hay marca del vehículo, cargando todos los servicios");
            }
        } catch (\Exception $e) {
            Log::error("[AgendarCita] Error al recargar servicios por marca: " . $e->getMessage());
        }
    }

    /**
     * Fuerza la recarga del calendario
     */
    public function recargarCalendario(): void
    {
        $this->generarCalendario();
    }

    /**
     * Alterna la selección de un servicio
     */
    public function toggleServicio(string $servicio): void
    {
        if (in_array($servicio, $this->serviciosSeleccionados)) {
            // Remover el servicio
            $this->serviciosSeleccionados = array_filter($this->serviciosSeleccionados, function ($s) use ($servicio) {
                return $s !== $servicio;
            });

            // Limpiar campos relacionados si se deselecciona
            if ($servicio === 'Mantenimiento periódico') {
                $this->tipoMantenimiento = '';
                $this->modalidadServicio = '';
            }
        } else {
            // Agregar el servicio
            $this->serviciosSeleccionados[] = $servicio;
        }

        Log::info('[AgendarCita] Servicios seleccionados actualizados: ' . json_encode($this->serviciosSeleccionados));
    }

    /**
     * Verifica si un servicio está seleccionado
     */
    public function isServicioSeleccionado(string $servicio): bool
    {
        return in_array($servicio, $this->serviciosSeleccionados);
    }

    /**
     * Cargar los locales desde la tabla de locales filtrados por marca del vehículo
     */
    protected function cargarLocales(): void
    {
        // Obtener la marca del vehículo para filtrar locales
        $marcaVehiculo = $this->obtenerMarcaVehiculo();

        Log::info("[AgendarCita] Marca del vehículo detectada: {$marcaVehiculo}");

        // Construir la consulta base para locales activos
        $query = \App\Models\Local::where('is_active', true);

        // Filtrar por marca si se detectó una marca válida
        if ($marcaVehiculo) {
            $query->where('brand', $marcaVehiculo);
            Log::info("[AgendarCita] Filtrando locales por marca: {$marcaVehiculo}");
        } else {
            Log::info('[AgendarCita] No se detectó marca del vehículo, mostrando todos los locales activos');
        }

        $localesActivos = $query->get();

        Log::info('[AgendarCita] Consultando locales activos. Total encontrados: ' . $localesActivos->count());

        // Verificar si hay locales en la base de datos
        $todosLosLocales = \App\Models\Local::all();
        Log::info('[AgendarCita] Total de locales en la base de datos: ' . $todosLosLocales->count());

        foreach ($todosLosLocales as $index => $local) {
            Log::info("[AgendarCita] Local #{$index} en DB: ID: {$local->id}, Código: {$local->code}, Nombre: {$local->name}, Marca: {$local->brand}, Activo: " . ($local->is_active ? 'Sí' : 'No'));
        }

        if ($localesActivos->isNotEmpty()) {
            $this->locales = [];

            foreach ($localesActivos as $local) {
                $key = $local->code;
                $this->locales[$key] = [
                    'nombre' => $local->name,
                    'direccion' => $local->address,
                    'telefono' => $local->phone,
                    'opening_time' => $local->opening_time ?: '08:00',
                    'closing_time' => $local->closing_time ?: '17:00',
                    'waze_url' => $local->waze_url,
                    'maps_url' => $local->maps_url,
                    'id' => $local->id,
                    'brand' => $local->brand,
                ];

                Log::info("[AgendarCita] Local cargado: Código: {$key}, ID: {$local->id}, Nombre: {$local->name}, Marca: {$local->brand}, Horario: {$local->opening_time} - {$local->closing_time}");
            }

            Log::info('[AgendarCita] Locales cargados: ' . count($this->locales));

            // Establecer el primer local como seleccionado por defecto
            if (empty($this->localSeleccionado) && ! empty($this->locales)) {
                $this->localSeleccionado = array_key_first($this->locales);
                Log::info("[AgendarCita] Local seleccionado por defecto: {$this->localSeleccionado}, ID: {$this->locales[$this->localSeleccionado]['id']}");

                // Regenerar el calendario para actualizar la disponibilidad según el local seleccionado
                if (isset($this->mesActual) && isset($this->anoActual)) {
                    $this->generarCalendario();
                }
            }
        } else {
            Log::warning("[AgendarCita] No se encontraron locales activos para la marca: {$marcaVehiculo}");

            // Si no hay locales para la marca específica, mostrar todos los locales activos como fallback
            if ($marcaVehiculo) {
                Log::info('[AgendarCita] Fallback: Cargando todos los locales activos');
                $localesActivos = \App\Models\Local::where('is_active', true)->get();

                if ($localesActivos->isNotEmpty()) {
                    $this->locales = [];
                    foreach ($localesActivos as $local) {
                        $key = $local->code;
                        $this->locales[$key] = [
                            'nombre' => $local->name,
                            'direccion' => $local->address,
                            'telefono' => $local->phone,
                            'opening_time' => $local->opening_time ?: '08:00',
                            'closing_time' => $local->closing_time ?: '17:00',
                            'waze_url' => $local->waze_url,
                            'maps_url' => $local->maps_url,
                            'id' => $local->id,
                            'brand' => $local->brand,
                        ];
                    }

                    // Establecer el primer local como seleccionado por defecto
                    if (empty($this->localSeleccionado) && ! empty($this->locales)) {
                        $this->localSeleccionado = array_key_first($this->locales);
                    }
                }
            } else {
                $this->locales = [];
            }
        }
    }

    /**
     * Obtener la marca del vehículo para filtrar locales
     */
    protected function obtenerMarcaVehiculo(): ?string
    {
        // Verificar si tenemos datos del vehículo
        if (empty($this->vehiculo)) {
            Log::info('[AgendarCita] No hay datos del vehículo disponibles');

            return null;
        }

        // Intentar obtener la marca desde diferentes fuentes
        $marca = null;

        // 1. Desde brand_name del vehículo
        if (! empty($this->vehiculo['brand_name'])) {
            $marca = $this->vehiculo['brand_name'];
            Log::info("[AgendarCita] Marca obtenida desde brand_name: {$marca}");
        }
        // 2. Desde brand_code del vehículo
        elseif (! empty($this->vehiculo['brand_code'])) {
            $marca = $this->vehiculo['brand_code'];
            Log::info("[AgendarCita] Marca obtenida desde brand_code: {$marca}");
        }
        // 3. Desde marca del vehículo (campo directo)
        elseif (! empty($this->vehiculo['marca'])) {
            $marca = $this->vehiculo['marca'];
            Log::info("[AgendarCita] Marca obtenida desde marca: {$marca}");
        }

        // Normalizar la marca a los valores esperados en la base de datos
        if ($marca) {
            $marca = $this->normalizarMarca($marca);
            Log::info("[AgendarCita] Marca normalizada: {$marca}");
        }

        return $marca;
    }

    /**
     * Normalizar la marca del vehículo a los valores esperados
     */
    protected function normalizarMarca(string $marca): ?string
    {
        $marca = strtolower(trim($marca));

        // Mapear diferentes variaciones de marca a los valores estándar
        $mapeoMarcas = [
            'toyota' => 'Toyota',
            'lexus' => 'Lexus',
            'hino' => 'Hino',
            'mitsubishi' => 'Toyota', // Fallback si es Mitsubishi
            'mit' => 'Toyota',
        ];

        foreach ($mapeoMarcas as $variacion => $marcaEstandar) {
            if (str_contains($marca, $variacion)) {
                Log::info("[AgendarCita] Marca '{$marca}' mapeada a '{$marcaEstandar}'");

                return $marcaEstandar;
            }
        }

        Log::warning("[AgendarCita] Marca '{$marca}' no reconocida, no se aplicará filtro");

        return null;
    }

    /**
     * Cargar los servicios adicionales desde la base de datos
     */
    protected function cargarServiciosAdicionales(): void
    {
        Log::info('[AgendarCita] === INICIANDO CARGA DE SERVICIOS ADICIONALES ===');

        // Inicializar opciones de servicios adicionales
        $this->opcionesServiciosAdicionales = [];

        // Cargar servicios adicionales del maestro
        try {
            $query = AdditionalService::where('is_active', true);
            
            // Filtrar por marca del vehículo si está disponible
            if (!empty($this->vehiculo['marca'])) {
                $marcaVehiculo = strtoupper(trim($this->vehiculo['marca']));
                Log::info("[AgendarCita] Filtrando servicios adicionales del maestro por marca: {$marcaVehiculo}");
                
                // Usar el nuevo scope para filtrar por marca en JSON
                $query->porMarca($marcaVehiculo);
            }
            
            $serviciosAdicionales = $query->get();
            foreach ($serviciosAdicionales as $servicio) {
                $this->opcionesServiciosAdicionales['servicio_' . $servicio->id] = $servicio->name;
            }
            Log::info('[AgendarCita] Servicios adicionales del maestro filtrados por marca cargados: ' . count($serviciosAdicionales));
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar servicios adicionales del maestro: ' . $e->getMessage());
        }

        // Cargar campañas activas
        $this->cargarCampanas();
    }

    protected function cargarModalidadesDisponibles(): void
    {
        try {
            Log::info("=== [AgendarCita] INICIANDO CARGA DE MODALIDADES ===");
            Log::info("[AgendarCita] Datos del vehículo actual:", $this->vehiculo);
            Log::info("[AgendarCita] Local seleccionado: {$this->localSeleccionado}");
            Log::info("[AgendarCita] Tipo de mantenimiento: {$this->tipoMantenimiento}");
            
            // Verificar específicamente el campo NUMMOT
            $nummot = $this->vehiculo['nummot'] ?? '';
            Log::info("[AgendarCita] 🔍 Campo NUMMOT del vehículo: '{$nummot}'");
            
            if (!empty($nummot)) {
                $codigoMotorExtraido = $this->extraerCodigoMotor($nummot);
                Log::info("[AgendarCita] 🔧 Código de motor extraído: '{$codigoMotorExtraido}'");
            } else {
                Log::warning("[AgendarCita] ⚠️ Campo NUMMOT está vacío o no existe");
            }

            // Modalidad Regular siempre está disponible
            $this->modalidadesDisponibles = [
                'Regular' => 'Regular',
            ];

            // Verificar si Express está disponible para este vehículo y local
            $this->tipoExpress = null; // Reset
            Log::info("[AgendarCita] 🚀 Verificando si Express está disponible...");
            $expressDisponible = $this->esExpressDisponible();
            Log::info("[AgendarCita] 📊 Resultado Express disponible: " . ($expressDisponible ? 'SÍ' : 'NO'));

            if ($expressDisponible) {
                Log::info("[AgendarCita] ✅ Express está disponible! Obteniendo tipo de servicio...");
                
                // --- Lógica igual a esExpressDisponible para obtener el type correcto ---
                $nombreLocal = '';
                try {
                    $localObj = \App\Models\Local::where('code', $this->localSeleccionado)->first();
                    $nombreLocal = $localObj ? $localObj->name : '';
                } catch (\Exception $e) {
                }

                $formatosABuscar = [$this->tipoMantenimiento];
                if (strpos($this->tipoMantenimiento, 'Mantenimiento') !== false) {
                    $formato1 = str_replace('Mantenimiento ', '', $this->tipoMantenimiento);
                    $formato2 = str_replace('000 Km', ',000 Km', $formato1);
                    $formatosABuscar[] = $formato1;
                    $formatosABuscar[] = $formato2;
                }

                $vehiculosExpress = VehiculoExpress::where('is_active', true)
                    ->where('model', 'like', "%{$this->vehiculo['modelo']}%")
                    ->where('brand', 'like', "%{$this->vehiculo['marca']}%")
                    ->where(function ($query) use ($nombreLocal) {
                        $query->where('premises', $this->localSeleccionado)
                            ->orWhere('premises', $nombreLocal);
                    })
                    ->get();

                $vehiculoExpress = null;
                foreach ($vehiculosExpress as $vehiculo) {
                    $mantenimientos = $vehiculo->maintenance;
                    if (is_string($mantenimientos)) {
                        if (str_starts_with($mantenimientos, '[') || str_starts_with($mantenimientos, '{')) {
                            $decoded = json_decode($mantenimientos, true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $mantenimientos = $decoded;
                            } else {
                                $mantenimientos = [$mantenimientos];
                            }
                        } else {
                            $mantenimientos = [$mantenimientos];
                        }
                    } elseif (! is_array($mantenimientos)) {
                        $mantenimientos = [$mantenimientos];
                    }
                    foreach ($formatosABuscar as $formato) {
                        foreach ($mantenimientos as $mantenimientoVehiculo) {
                            if ($formato === $mantenimientoVehiculo) {
                                $vehiculoExpress = $vehiculo;
                                break 3;
                            }
                        }
                    }
                }
                $this->tipoExpress = $vehiculoExpress?->type ?? null;
                // --- Fin lógica ---
                $this->modalidadesDisponibles['Mantenimiento Express'] = 'Mantenimiento Express';
            }
        } catch (\Exception $e) {
            // En caso de error, solo mostrar Regular
            $this->modalidadesDisponibles = [
                'Regular' => 'Regular',
            ];
        }
    }


    /**
     * Cargar los tipos de mantenimiento disponibles desde la base de datos
     */
    protected function cargarTiposMantenimiento(): void
    {
        try {
            // Filtrar por marca del vehículo si está disponible
            if (!empty($this->vehiculo['marca'])) {
                $marcaVehiculo = strtoupper(trim($this->vehiculo['marca']));
                $modeloVehiculo = trim($this->vehiculo['modelo'] ?? '');
                
                Log::info("[AgendarCita] Filtrando tipos de mantenimiento - Marca: {$marcaVehiculo}, Modelo: {$modeloVehiculo}");
                
                // PRIORIDAD 1: Buscar mantenimientos específicos por tipo_valor_trabajo
                $mantenimientosPorTipoValorTrabajo = [];
                $tipoValorTrabajo = $this->vehiculo['tipo_valor_trabajo'] ?? '';
                
                if (!empty($tipoValorTrabajo)) {
                    $mantenimientosPorTipoValorTrabajo = \App\Models\ModelMaintenance::where('is_active', true)
                        ->where('brand', $marcaVehiculo)
                        ->where('tipo_valor_trabajo', $tipoValorTrabajo)
                        ->orderBy('kilometers')
                        ->pluck('name', 'id')
                        ->toArray();
                        
                    Log::info("[AgendarCita] Mantenimientos específicos por tipo_valor_trabajo encontrados: " . count($mantenimientosPorTipoValorTrabajo) . " para {$tipoValorTrabajo}");
                }
                
                if (!empty($mantenimientosPorTipoValorTrabajo)) {
                    // Si hay mantenimientos específicos para el tipo_valor_trabajo, usar SOLO esos (PRIORIDAD 1)
                    $this->tiposMantenimientoDisponibles = $mantenimientosPorTipoValorTrabajo;
                    Log::info("[AgendarCita] ✅ Usando mantenimientos específicos por tipo_valor_trabajo ({$tipoValorTrabajo}): " . count($this->tiposMantenimientoDisponibles));
                } else {
                    // PRIORIDAD 2: Si no hay mantenimientos específicos por tipo_valor_trabajo, usar los generales por marca
                    $tiposMantenimiento = MaintenanceType::where('is_active', true)
                        ->where('brand', $marcaVehiculo)
                        ->orderBy('kilometers')
                        ->pluck('name', 'id')
                        ->toArray();
                        
                    $this->tiposMantenimientoDisponibles = $tiposMantenimiento;
                    Log::info("[AgendarCita] ⚠️ No hay mantenimientos específicos por tipo_valor_trabajo, usando mantenimientos generales por marca: " . count($this->tiposMantenimientoDisponibles));
                }
            } else {
                // Si no hay marca del vehículo, cargar todos los mantenimientos generales
                $this->tiposMantenimientoDisponibles = MaintenanceType::getActivosParaSelector();
                Log::info('[AgendarCita] Tipos de mantenimiento cargados (sin filtro de marca): ' . count($this->tiposMantenimientoDisponibles));
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar tipos de mantenimiento: ' . $e->getMessage());
            $this->tiposMantenimientoDisponibles = [];
        }
    }


    protected function cargarServiciosAdicionalesDisponibles(): void
    {
        try {
            $query = AdditionalService::where('is_active', true);
            
            // Filtrar por marca del vehículo si está disponible
            if (!empty($this->vehiculo['marca'])) {
                $marcaVehiculo = strtoupper(trim($this->vehiculo['marca']));
                Log::info("[AgendarCita] Filtrando servicios adicionales por marca: {$marcaVehiculo}");
                
                // Usar el nuevo scope para filtrar por marca en JSON
                $query->porMarca($marcaVehiculo);
            }
            
            $servicios = $query->orderBy('name')->get();
            
            // Filtrar los servicios PQLEX y PQRBA si hay tipo de mantenimiento seleccionado
            if ($this->tipoMantenimiento) {
                $servicios = $servicios->filter(function ($servicio) {
                    return !in_array($servicio->code, ['PQLEX', 'PQRBA']);
                });
            }
            
            $this->serviciosAdicionalesDisponibles = $servicios->pluck('name', 'id')->toArray();
            Log::info('[AgendarCita] Servicios adicionales filtrados por marca cargados: ' . count($this->serviciosAdicionalesDisponibles));
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar servicios adicionales: ' . $e->getMessage());
            $this->serviciosAdicionalesDisponibles = [];
        }
    }


    /**
     * Método que se ejecuta cuando se actualiza la selección del servicio adicional
     */
    public function updatedServicioAdicionalSeleccionado($value): void
    {
        if (! empty($value) && ! in_array($value, $this->serviciosAdicionales)) {
            // Agregar el servicio seleccionado a la lista de servicios adicionales
            $this->serviciosAdicionales[] = $value;
            Log::info("[AgendarCita] Servicio adicional agregado: {$value}");

            // Limpiar la selección para permitir agregar más servicios
            $this->servicioAdicionalSeleccionado = '';

            // Actualizar servicios extras elegidos
            $this->actualizarServiciosExtrasElegidos();
        }
    }

    /**
     * Eliminar un servicio adicional de la lista
     */
    public function eliminarServicioAdicional($servicio): void
    {
        $this->serviciosAdicionales = array_values(array_filter($this->serviciosAdicionales, function ($item) use ($servicio) {
            return $item !== $servicio;
        }));
        Log::info("[AgendarCita] Servicio adicional eliminado: {$servicio}");

        // Actualizar servicios extras elegidos
        $this->actualizarServiciosExtrasElegidos();
    }

    /**
     * Método que se ejecuta cuando se actualiza la campaña seleccionada
     */
    public function updatedCampanaSeleccionada(): void
    {
        // Actualizar servicios extras elegidos
        $this->actualizarServiciosExtrasElegidos();
    }

    /**
     * Actualizar el campo oculto de servicios extras elegidos
     */
    protected function actualizarServiciosExtrasElegidos(): void
    {
        $serviciosExtras = [];

        // Agregar servicios adicionales seleccionados
        if (!empty($this->serviciosAdicionales)) {
            $serviciosTexto = [];
            foreach ($this->serviciosAdicionales as $servicio) {
                $nombreServicio = $this->opcionesServiciosAdicionales[$servicio] ?? $servicio;
                $serviciosTexto[] = $nombreServicio;
            }
            if (!empty($serviciosTexto)) {
                $serviciosExtras[] = implode(', ', $serviciosTexto);
            }
        }

        // Agregar campaña seleccionada
        if (!empty($this->campanaSeleccionada)) {
            $campana = collect($this->campanasDisponibles)->firstWhere('id', $this->campanaSeleccionada);
            if ($campana) {
                $serviciosExtras[] = $campana['titulo'];
            }
        }

        $this->serviciosExtrasElegidos = implode(', ', $serviciosExtras);

        Log::info("[AgendarCita] Servicios extras actualizados: {$this->serviciosExtrasElegidos}");
    }

    /**
     * Generar comentario completo para enviar a la API
     */
    protected function generarComentarioCompleto(): string
    {
        $comentarioFinal = '';

        // ✅ DETECTAR CLIENTE WILDCARD PARA INCLUIR CAMPOS ADICIONALES
        $user = Auth::user();
        $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

        // ✅ PARA CLIENTES WILDCARD: Incluir servicios seleccionados y tipo de mantenimiento
        if ($isWildcardClient) {
            // Agregar servicios seleccionados
            if (!empty($this->serviciosSeleccionados)) {
                $serviciosTexto = implode(', ', $this->serviciosSeleccionados);
                $comentarioFinal .= "Servicios: " . $serviciosTexto;
            }

            // Agregar tipo de mantenimiento
            if (!empty($this->tipoMantenimiento)) {
                if (!empty($comentarioFinal)) {
                    $comentarioFinal .= "\n";
                }
                $comentarioFinal .= "Mantenimiento: " . $this->tipoMantenimiento;
            }

            // Agregar modalidad de servicio
            if (!empty($this->modalidadServicio)) {
                if (!empty($comentarioFinal)) {
                    $comentarioFinal .= "\n";
                }
                $comentarioFinal .= "Modalidad: " . $this->modalidadServicio;
            }
        }

        // ✅ SOLO PARA CLIENTES WILDCARD: Comentarios
        if ($isWildcardClient && !empty($this->comentarios)) {
            if (!empty($comentarioFinal)) {
                $comentarioFinal .= "\n";
            }
            $comentarioFinal .= "Comentarios: " . $this->comentarios;
        }

         // ✅ PARA CLIENTES WILDCARD: Agregar teléfono y correo al final
         if ($isWildcardClient) {
            // Agregar teléfono del cliente
            if (!empty($user->phone)) {
                if (!empty($comentarioFinal)) {
                    $comentarioFinal .= "\n";
                }
                $comentarioFinal .= "Teléfono: " . $user->phone;
            }

            // Agregar correo del cliente  
            if (!empty($user->email)) {
                if (!empty($comentarioFinal)) {
                    $comentarioFinal .= "\n";
                }
                $comentarioFinal .= "Correo: " . $user->email;
            }
        }

        // ✅ SOLO PARA CLIENTES WILDCARD: Servicios extras elegidos
        if ($isWildcardClient && !empty($this->serviciosExtrasElegidos)) {
            if (!empty($comentarioFinal)) {
                $comentarioFinal .= "\n";
            }
            $comentarioFinal .= "Servicios extras elegidos: " . $this->serviciosExtrasElegidos;
        }

        // ✅ SOLO PARA CLIENTES WILDCARD: Campañas seleccionadas
        if ($isWildcardClient && !empty($this->campanaSeleccionada)) {
            $campana = collect($this->campanasDisponibles)->firstWhere('id', $this->campanaSeleccionada);
            if ($campana) {
                if (!empty($comentarioFinal)) {
                    $comentarioFinal .= "\n";
                }
                $comentarioFinal .= "Campañas: " . $campana['titulo'];
            }
        }
 // ✅ PARA CLIENTES NORMALES: Orden específico
 if (!$isWildcardClient) {
    // 1. Mantenimiento
    if (!empty($this->tipoMantenimiento)) {
        if (!empty($comentarioFinal)) {
            $comentarioFinal .= "\n";
        }
        $comentarioFinal .= "Mantenimiento: " . $this->tipoMantenimiento;
    }
    
    // 2. Servicios extras elegidos
    if (!empty($this->serviciosExtrasElegidos)) {
        if (!empty($comentarioFinal)) {
            $comentarioFinal .= "\n";
        }
        $comentarioFinal .= "Servicios extras elegidos: " . $this->serviciosExtrasElegidos;
    }
    
    // 3. Comentarios
    if (!empty($this->comentarios)) {
        if (!empty($comentarioFinal)) {
            $comentarioFinal .= "\n";
        }
        $comentarioFinal .= "Comentarios: " . $this->comentarios;
    }
}
        return $comentarioFinal;
    }

    /**
     * Extrae el código de motor de los primeros 3 caracteres del campo NUMMOT
     */
    protected function extraerCodigoMotor(string $nummot): string
    {
        // Extraer los primeros 3 caracteres del NUMMOT
        // Ejemplo: "2GD-4834591" -> "2GD"
        return strtoupper(substr(trim($nummot), 0, 3));
    }

    /**
     * SOLUCIÓN TEMPORAL: Extrae el código de motor del campo MODVER de SAP
     * Ejemplo: "4X2 D/C 2GD" -> "2GD-TEMP"
     */
    protected function extraerCodigoMotorDeModver(string $modver): string
    {
        // Buscar patrones de código de motor al final del MODVER
        // Patrones comunes: 2GD, 3FR, 1ZZ, etc.
        if (preg_match('/\b([0-9][A-Z]{2})\b/', strtoupper(trim($modver)), $matches)) {
            $codigoExtraido = $matches[1] . '-TEMP'; // Agregar -TEMP para identificar que es temporal
            Log::info("[AgendarCita] 🔧 Código de motor extraído del MODVER '{$modver}': '{$codigoExtraido}'");
            return $codigoExtraido;
        }
        
        Log::warning("[AgendarCita] ⚠️ No se pudo extraer código de motor del MODVER: '{$modver}'");
        return '';
    }

    protected function esExpressDisponible(): bool
    {
        try {
            Log::info("=== [AgendarCita] VERIFICANDO SI EXPRESS ESTÁ DISPONIBLE ===");
            
            // Si no hay vehículo, local o tipo de mantenimiento seleccionado, Express no está disponible
            if (empty($this->vehiculo) || empty($this->localSeleccionado) || empty($this->tipoMantenimiento)) {
                Log::warning("[AgendarCita] ❌ Faltan datos básicos:");
                Log::warning("- Vehículo vacío: " . (empty($this->vehiculo) ? 'SÍ' : 'NO'));
                Log::warning("- Local vacío: " . (empty($this->localSeleccionado) ? 'SÍ' : 'NO'));
                Log::warning("- Tipo mantenimiento vacío: " . (empty($this->tipoMantenimiento) ? 'SÍ' : 'NO'));
                return false;
            }

            $modelo = $this->vehiculo['modelo'] ?? '';
            $marca = $this->vehiculo['marca'] ?? '';
            $nummot = $this->vehiculo['nummot'] ?? '';

            Log::info("[AgendarCita] 📋 Datos del vehículo para verificación:");
            Log::info("- Modelo: '{$modelo}'");
            Log::info("- Marca: '{$marca}'");
            Log::info("- NUMMOT: '{$nummot}'");
            Log::info("- Local seleccionado: '{$this->localSeleccionado}'");
            Log::info("- Tipo mantenimiento: '{$this->tipoMantenimiento}'");

            if (empty($modelo) || empty($marca)) {
                Log::warning("[AgendarCita] ❌ Modelo o marca vacíos");
                return false;
            }

            // Extraer código de motor de NUMMOT (primeros 3 caracteres)
            $codigoMotor = '';
            if (!empty($nummot)) {
                $codigoMotor = $this->extraerCodigoMotor($nummot);
                Log::info("[AgendarCita] 🔧 Código de motor extraído de NUMMOT '{$nummot}': '{$codigoMotor}'");
            } else {
                Log::warning("[AgendarCita] ⚠️ Campo NUMMOT está vacío o no existe - NO PUEDE SER EXPRESS");
                return false; // Sin código de motor, no puede ser Express
            }

            // Obtener el nombre del local seleccionado
            $nombreLocal = '';
            try {
                $localObj = \App\Models\Local::where('code', $this->localSeleccionado)->first();
                $nombreLocal = $localObj ? $localObj->name : '';
            } catch (\Exception $e) {
                // Silenciar error
            }

            // Normalizar el tipo de mantenimiento para la búsqueda
            $formatosABuscar = [
                $this->tipoMantenimiento, // Formato original
            ];

            // Si contiene "Mantenimiento", agregar variaciones
            if (strpos($this->tipoMantenimiento, 'Mantenimiento') !== false) {
                $formato1 = str_replace('Mantenimiento ', '', $this->tipoMantenimiento);
                $formato2 = str_replace('000 Km', ',000 Km', $formato1);
                $formatosABuscar[] = $formato1;
                $formatosABuscar[] = $formato2;
            }

            // Buscar vehículos que coincidan con modelo, marca, código de motor y local
            $vehiculosExpress = VehiculoExpress::where('is_active', true)
                ->where('model', 'like', "%{$modelo}%")
                ->where('brand', 'like', "%{$marca}%")
                ->where(function ($query) use ($nombreLocal) {
                    $query->where('premises', $this->localSeleccionado)  // Buscar por código
                        ->orWhere('premises', $nombreLocal);            // Buscar por nombre
                })
                ->get();

            Log::info("[AgendarCita] 🔍 Filtros aplicados - Modelo: {$modelo}, Marca: {$marca}, Código Motor: {$codigoMotor}, Local: {$this->localSeleccionado}/{$nombreLocal}");
            Log::info("[AgendarCita] 📊 Total vehículos Express encontrados en BD: " . $vehiculosExpress->count());

            if ($vehiculosExpress->isEmpty()) {
                Log::warning("[AgendarCita] ❌ No se encontraron vehículos Express que coincidan con los filtros básicos");
                return false;
            }

            $vehiculoExpress = null;
            foreach ($vehiculosExpress as $index => $vehiculo) {
                Log::info("[AgendarCita] 🔄 Verificando vehículo Express #{$index} - ID: {$vehiculo->id}");
                Log::info("[AgendarCita] - Model: {$vehiculo->model}");
                Log::info("[AgendarCita] - Brand: {$vehiculo->brand}");
                Log::info("[AgendarCita] - Motor Codes (year): " . json_encode($vehiculo->year));
                Log::info("[AgendarCita] - Maintenance: " . json_encode($vehiculo->maintenance));
                Log::info("[AgendarCita] - Premises: {$vehiculo->premises}");
                Log::info("[AgendarCita] - Type: {$vehiculo->type}");
                
                // 1. Verificar código de motor
                $codigosMotorVehiculo = $vehiculo->year; // El campo year ahora contiene códigos de motor
                $codigoMotorCoincide = false;
                
                if (is_array($codigosMotorVehiculo)) {
                    // Si es array, buscar coincidencia
                    $codigoMotorCoincide = in_array($codigoMotor, $codigosMotorVehiculo);
                    Log::info("[AgendarCita] 🔧 Verificando código motor en array: " . json_encode($codigosMotorVehiculo) . " - Buscando: '{$codigoMotor}' - Resultado: " . ($codigoMotorCoincide ? 'COINCIDE' : 'NO COINCIDE'));
                } elseif (is_string($codigosMotorVehiculo)) {
                    // Si es string, comparar directamente
                    $codigoMotorCoincide = ($codigoMotor === strtoupper(trim($codigosMotorVehiculo)));
                    Log::info("[AgendarCita] 🔧 Verificando código motor como string: '{$codigosMotorVehiculo}' vs '{$codigoMotor}' - Resultado: " . ($codigoMotorCoincide ? 'COINCIDE' : 'NO COINCIDE'));
                } else {
                    Log::warning("[AgendarCita] ⚠️ Código de motor tiene formato inesperado: " . gettype($codigosMotorVehiculo));
                }
                
                if (!$codigoMotorCoincide) {
                    Log::info("[AgendarCita] ❌ Código de motor no coincide. Esperado: '{$codigoMotor}', Configurado: " . json_encode($codigosMotorVehiculo));
                    continue; // Saltar este vehículo si el código de motor no coincide
                }
                
                Log::info("[AgendarCita] ✅ Código de motor coincide: '{$codigoMotor}'");

                // 2. Verificar mantenimiento
                $mantenimientos = $vehiculo->maintenance;
                if (is_string($mantenimientos)) {
                    if (str_starts_with($mantenimientos, '[') || str_starts_with($mantenimientos, '{')) {
                        $decoded = json_decode($mantenimientos, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $mantenimientos = $decoded;
                        } else {
                            $mantenimientos = [$mantenimientos];
                        }
                    } else {
                        $mantenimientos = [$mantenimientos];
                    }
                } elseif (! is_array($mantenimientos)) {
                    $mantenimientos = [$mantenimientos];
                }

                // 3. Verificar si el tipo de mantenimiento coincide
                foreach ($formatosABuscar as $formato) {
                    foreach ($mantenimientos as $mantenimientoVehiculo) {
                        if ($formato === $mantenimientoVehiculo) {
                            Log::info("[AgendarCita] ✅ Todos los filtros coinciden - Modelo: {$modelo}, Marca: {$marca}, Código Motor: {$codigoMotor}, Mantenimiento: {$formato}, Local: {$this->localSeleccionado}");
                            $vehiculoExpress = $vehiculo;
                            break 3;
                        }
                    }
                }
                
                if ($vehiculoExpress) {
                    break; // Ya encontramos coincidencia, salir del loop
                }
            }

            return $vehiculoExpress !== null;
        } catch (\Exception $e) {
            Log::error("[AgendarCita] Error en esExpressDisponible: " . $e->getMessage());
            return false;
        }
    }



    /**
     * Cargar las campañas activas desde la base de datos
     * Filtradas por modelo del vehículo y local seleccionado
     */
    protected function cargarCampanas(): void
    {
        try {
            Log::info('[AgendarCita] Iniciando carga de campañas filtradas');
            Log::info('[AgendarCita] Vehículo seleccionado: ' . json_encode($this->vehiculo ?? []));
            Log::info('[AgendarCita] Local seleccionado: ' . ($this->localSeleccionado ?? 'ninguno'));

            // Verificar específicamente la campaña 1 para depuración
            $this->verificarCampanaEspecifica('1');

            // Obtener campañas activas con filtros inteligentes
            $query = Campana::where('status', 'Activo');
            
            // Filtrar por marca del vehículo si está disponible
            if (!empty($this->vehiculo['marca'])) {
                $marcaVehiculo = strtoupper(trim($this->vehiculo['marca']));
                Log::info("[AgendarCita] Filtrando campañas por marca: {$marcaVehiculo}");
                $query->where('brand', $marcaVehiculo);
            }

            // Filtrar por modelo del vehículo si está disponible
            if (! empty($this->vehiculo['modelo'])) {
                $modeloVehiculo = $this->vehiculo['modelo'];
                Log::info("[AgendarCita] Filtrando campañas por modelo: {$modeloVehiculo}");

                $query->whereExists(function ($q) use ($modeloVehiculo) {
                    $q->select(DB::raw(1))
                        ->from('campaign_models')
                        ->join('models', 'campaign_models.model_id', '=', 'models.id')
                        ->whereColumn('campaign_models.campaign_id', 'campaigns.id')
                        ->where('models.name', 'like', "%{$modeloVehiculo}%");
                });
            }

            // Filtrar por local si está seleccionado
            if (! empty($this->localSeleccionado)) {
                Log::info("[AgendarCita] Filtrando campañas por local: {$this->localSeleccionado}");

                $query->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('campaign_premises')
                        ->whereColumn('campaign_premises.campaign_id', 'campaigns.id')
                        ->where('campaign_premises.premise_code', $this->localSeleccionado);
                });
            }

            // Filtrar por año del vehículo si está disponible Y si la campaña tiene años específicos
            if (! empty($this->vehiculo['anio']) && is_numeric($this->vehiculo['anio'])) {
                $anioVehiculo = (int) $this->vehiculo['anio'];
                Log::info("[AgendarCita] Filtrando campañas por año: {$anioVehiculo}");

                $query->where(function ($q) use ($anioVehiculo) {
                    // Incluir campañas que no tienen años específicos (aplican a todos los años)
                    $q->whereNotExists(function ($subQ) {
                        $subQ->select(DB::raw(1))
                            ->from('campaign_years')
                            ->whereColumn('campaign_years.campaign_id', 'campaigns.id');
                    })
                        // O campañas que sí tienen el año específico del vehículo
                        ->orWhereExists(function ($subQ) use ($anioVehiculo) {
                            $subQ->select(DB::raw(1))
                                ->from('campaign_years')
                                ->whereColumn('campaign_years.campaign_id', 'campaigns.id')
                                ->where('campaign_years.year', $anioVehiculo);
                        });
                });
            }

            $campanas = $query->get();

            Log::info('[AgendarCita] Campañas filtradas encontradas: ' . $campanas->count());

            // Verificar si hay campañas activas en la base de datos
            $todasLasCampanas = Campana::where('status', 'active')->get();
            Log::info('[AgendarCita] Total de campañas activas en la base de datos: ' . $todasLasCampanas->count());

            // No reinicializar opcionesServiciosAdicionales aquí para mantener los servicios del maestro
            // $this->opcionesServiciosAdicionales = [];

            foreach ($todasLasCampanas as $index => $campana) {
                Log::info("[AgendarCita] Campaña #{$index} en DB: ID: {$campana->id}, Título: {$campana->title}, Estado: {$campana->status}, Fecha inicio: {$campana->start_date}, Fecha fin: {$campana->end_date}");

                // Verificar si tiene imagen
                try {
                    $imagen = DB::table('campaign_images')->where('campaign_id', $campana->id)->first();
                    $tieneImagen = $imagen ? "Sí (ID: {$imagen->id}, Ruta: {$imagen->route})" : 'No';
                    Log::info("[AgendarCita] Campaña #{$index} tiene imagen: {$tieneImagen}");
                } catch (\Exception $e) {
                    Log::error("[AgendarCita] Error al verificar imagen de campaña #{$index}: " . $e->getMessage());
                }

                // Verificar modelos asociados
                try {
                    $modelos = DB::table('campaign_models')
                        ->join('models', 'campaign_models.model_id', '=', 'models.id')
                        ->where('campaign_models.campaign_id', $campana->id)
                        ->pluck('models.name')
                        ->toArray();
                    Log::info("[AgendarCita] Campaña #{$index} modelos: " . (empty($modelos) ? 'Ninguno' : implode(', ', $modelos)));
                } catch (\Exception $e) {
                    Log::error("[AgendarCita] Error al verificar modelos de campaña #{$index}: " . $e->getMessage());
                }

                // Verificar años asociados
                try {
                    $anos = DB::table('campaign_years')
                        ->where('campaign_id', $campana->id)
                        ->pluck('year')
                        ->toArray();
                    Log::info("[AgendarCita] Campaña #{$index} años: " . (empty($anos) ? 'Ninguno' : implode(', ', $anos)));
                } catch (\Exception $e) {
                    Log::error("[AgendarCita] Error al verificar años de campaña #{$index}: " . $e->getMessage());
                }

                // Verificar locales asociados
                try {
                    $locales = DB::table('campaign_premises')
                        ->join('premises', 'campaign_premises.premise_code', '=', 'premises.code')
                        ->where('campaign_premises.campaign_id', $campana->id)
                        ->pluck('premises.name')
                        ->toArray();
                    Log::info("[AgendarCita] Campaña #{$index} locales: " . (empty($locales) ? 'Ninguno' : implode(', ', $locales)));
                } catch (\Exception $e) {
                    Log::error("[AgendarCita] Error al verificar locales de campaña #{$index}: " . $e->getMessage());
                }
            }

            if ($campanas->isNotEmpty()) {
                $this->campanasDisponibles = [];

                foreach ($campanas as $campana) {
                    // Verificar que la campaña esté activa
                    if ($campana->status !== 'Activo') {
                        Log::info("[AgendarCita] Campaña {$campana->id} no está activa (estado: {$campana->status}), omitiendo");

                        continue;
                    }

                    // Obtener la imagen de la campaña desde la tabla campaign_images
                    $imagenObj = DB::table('campaign_images')->where('campaign_id', $campana->id)->first();

                    // Construir la URL correcta para la imagen
                    if ($imagenObj && $imagenObj->route) {
                        try {
                            $imagen = $this->getImageUrl($imagenObj);
                            Log::info("[AgendarCita] Campaña {$campana->id} tiene imagen: {$imagenObj->route}, URL generada: {$imagen}");
                        } catch (\Exception $e) {
                            // Si hay algún error, usar una imagen por defecto
                            $imagen = asset('images/default-campaign.jpg');
                            Log::error('[AgendarCita] Error al generar URL de imagen: ' . $e->getMessage());
                        }
                    } else {
                        // Si no hay imagen, usar una imagen por defecto
                        $imagen = asset('images/default-campaign.jpg');
                        Log::info("[AgendarCita] Campaña {$campana->id} no tiene imagen, usando imagen por defecto");
                    }

                    $this->campanasDisponibles[] = [
                        'id' => $campana->id,
                        'titulo' => $campana->title,
                        'descripcion' => $campana->title, // Usamos el título como descripción ya que no hay campo de descripción
                        'imagen' => $imagen,
                        'fecha_inicio' => $campana->start_date,
                        'fecha_fin' => $campana->end_date,
                    ];

                    // Agregar al array de opciones de servicios adicionales para mostrar en el resumen
                    $this->opcionesServiciosAdicionales['campana_' . $campana->id] = $campana->title;

                    Log::info("[AgendarCita] Campaña cargada: ID: {$campana->id}, Título: {$campana->title}, Imagen: {$imagen}");
                }

                Log::info('[AgendarCita] Campañas disponibles cargadas: ' . count($this->campanasDisponibles));
            } else {
                Log::info('[AgendarCita] No se encontraron campañas activas');

                // Si no hay campañas en la base de datos, crear algunas campañas de prueba
                $this->campanasDisponibles = [];

                // Agregar al array de opciones de servicios adicionales para mostrar en el resumen
                foreach ($this->campanasDisponibles as $campana) {
                    $this->opcionesServiciosAdicionales['campana_' . $campana['id']] = $campana['titulo'];
                }

                Log::info('[AgendarCita] Creadas campañas de prueba: ' . count($this->campanasDisponibles));
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al cargar campañas: ' . $e->getMessage());

            // En caso de error, crear algunas campañas de prueba
            $this->campanasDisponibles = [];

            // Agregar al array de opciones de servicios adicionales para mostrar en el resumen
            foreach ($this->campanasDisponibles as $campana) {
                $this->opcionesServiciosAdicionales['campana_' . $campana['id']] = $campana['titulo'];
            }

            Log::info('[AgendarCita] Creadas campañas de prueba por error: ' . count($this->campanasDisponibles));
        }
    }

    /**
     * Verificar específicamente una campaña para depuración
     */
    protected function verificarCampanaEspecifica($codigoCampana): void
    {
        try {
            Log::info("[AgendarCita] === VERIFICACIÓN ESPECÍFICA DE CAMPAÑA {$codigoCampana} ===");

            // Buscar la campaña por ID (ya no hay código)
            $campana = Campana::where('id', $codigoCampana)->first();

            if (! $campana) {
                Log::warning("[AgendarCita] Campaña {$codigoCampana} no encontrada en la base de datos");

                return;
            }

            Log::info("[AgendarCita] Campaña encontrada: ID {$campana->id}, Título: {$campana->title}, Estado: {$campana->status}");

            // Verificar modelos asociados
            $modelosAsociados = DB::table('campaign_models')
                ->join('models', 'campaign_models.model_id', '=', 'models.id')
                ->where('campaign_models.campaign_id', $campana->id)
                ->select('models.name', 'models.code')
                ->get();

            Log::info("[AgendarCita] Modelos asociados a campaña {$codigoCampana}:");
            foreach ($modelosAsociados as $modelo) {
                Log::info("[AgendarCita]   - {$modelo->name} (código: {$modelo->code})");
            }

            // Verificar años asociados
            $anosAsociados = DB::table('campaign_years')
                ->where('campaign_id', $campana->id)
                ->pluck('year')
                ->toArray();

            Log::info("[AgendarCita] Años asociados a campaña {$codigoCampana}: " . implode(', ', $anosAsociados));

            // Verificar locales asociados
            $localesAsociados = DB::table('campaign_premises')
                ->join('premises', 'campaign_premises.premise_code', '=', 'premises.code')
                ->where('campaign_premises.campaign_id', $campana->id)
                ->select('premises.name', 'premises.code')
                ->get();

            Log::info("[AgendarCita] Locales asociados a campaña {$codigoCampana}:");
            foreach ($localesAsociados as $local) {
                Log::info("[AgendarCita]   - {$local->name} (código: {$local->code})");
            }

            // Verificar coincidencias con el vehículo actual
            $modeloVehiculo = $this->vehiculo['modelo'] ?? '';
            $anioVehiculo = $this->vehiculo['anio'] ?? '';
            $localSeleccionado = $this->localSeleccionado ?? '';

            Log::info('[AgendarCita] Comparación con vehículo actual:');
            Log::info("[AgendarCita]   Modelo vehículo: '{$modeloVehiculo}'");
            Log::info("[AgendarCita]   Año vehículo: '{$anioVehiculo}'");
            Log::info("[AgendarCita]   Local seleccionado: '{$localSeleccionado}'");

            // Verificar coincidencia de modelo
            $modeloCoincide = false;
            foreach ($modelosAsociados as $modelo) {
                if (
                    stripos($modelo->name, $modeloVehiculo) !== false ||
                    stripos($modeloVehiculo, $modelo->name) !== false
                ) {
                    $modeloCoincide = true;
                    Log::info("[AgendarCita]   ✓ Modelo coincide: '{$modelo->name}' contiene '{$modeloVehiculo}'");
                    break;
                }
            }

            if (! $modeloCoincide) {
                Log::warning('[AgendarCita]   ✗ Modelo NO coincide');
            }

            // Verificar coincidencia de año (si la campaña tiene años específicos)
            $anioCoincide = empty($anosAsociados) || in_array($anioVehiculo, $anosAsociados);
            if ($anioCoincide) {
                if (empty($anosAsociados)) {
                    Log::info("[AgendarCita]   ✓ Año coincide: Campaña aplica para todos los años");
                } else {
                    Log::info("[AgendarCita]   ✓ Año coincide: '{$anioVehiculo}'");
                }
            } else {
                Log::warning("[AgendarCita]   ✗ Año NO coincide: '{$anioVehiculo}' no está en [" . implode(', ', $anosAsociados) . ']');
            }

            // Verificar coincidencia de local
            $localCoincide = false;
            foreach ($localesAsociados as $local) {
                if ($local->code === $localSeleccionado) {
                    $localCoincide = true;
                    Log::info("[AgendarCita]   ✓ Local coincide: '{$local->code}'");
                    break;
                }
            }

            if (! $localCoincide) {
                Log::warning("[AgendarCita]   ✗ Local NO coincide: '{$localSeleccionado}' no está en la lista");
            }

            $aplicaCampana = $modeloCoincide && $anioCoincide && $localCoincide;
            Log::info("[AgendarCita] RESULTADO: Campaña {$codigoCampana} " . ($aplicaCampana ? 'SÍ APLICA' : 'NO APLICA'));
            Log::info('[AgendarCita] === FIN VERIFICACIÓN ESPECÍFICA ===');
        } catch (\Exception $e) {
            Log::error("[AgendarCita] Error al verificar campaña específica {$codigoCampana}: " . $e->getMessage());
        }
    }

    /**
     * Verificar si una campaña aplica para el vehículo y local seleccionados
     */
    protected function verificarCampanaAplicaParaVehiculo($campana): bool
    {
        try {
            $aplicaCampana = true;
            $razonesExclusion = [];

            // Verificar modelo del vehículo
            if (! empty($this->vehiculo['modelo'])) {
                $modeloVehiculo = $this->vehiculo['modelo'];
                $modelosAsociados = DB::table('campaign_models')
                    ->join('models', 'campaign_models.model_id', '=', 'models.id')
                    ->where('campaign_models.campaign_id', $campana->id)
                    ->pluck('models.name')
                    ->toArray();

                if (! empty($modelosAsociados)) {
                    $modeloCoincide = false;
                    foreach ($modelosAsociados as $modeloAsociado) {
                        if (
                            stripos($modeloAsociado, $modeloVehiculo) !== false ||
                            stripos($modeloVehiculo, $modeloAsociado) !== false
                        ) {
                            $modeloCoincide = true;
                            break;
                        }
                    }

                    if (! $modeloCoincide) {
                        $aplicaCampana = false;
                        $razonesExclusion[] = "Modelo '{$modeloVehiculo}' no coincide con modelos de campaña: " . implode(', ', $modelosAsociados);
                    }
                }
            }

            // Verificar local seleccionado
            if (! empty($this->localSeleccionado)) {
                $localesAsociados = DB::table('campaign_premises')
                    ->where('campaign_id', $campana->id)
                    ->pluck('premise_code')
                    ->toArray();

                if (! empty($localesAsociados) && ! in_array($this->localSeleccionado, $localesAsociados)) {
                    $aplicaCampana = false;
                    $razonesExclusion[] = "Local '{$this->localSeleccionado}' no está en la lista de locales de campaña: " . implode(', ', $localesAsociados);
                }
            }

            // Verificar año del vehículo (solo si la campaña tiene años específicos)
            if (! empty($this->vehiculo['anio']) && is_numeric($this->vehiculo['anio'])) {
                $anioVehiculo = (int) $this->vehiculo['anio'];
                $anosAsociados = DB::table('campaign_years')
                    ->where('campaign_id', $campana->id)
                    ->pluck('year')
                    ->toArray();

                // Solo verificar si la campaña tiene años específicos configurados
                if (! empty($anosAsociados) && ! in_array($anioVehiculo, $anosAsociados)) {
                    $aplicaCampana = false;
                    $razonesExclusion[] = "Año '{$anioVehiculo}' no está en la lista de años de campaña: " . implode(', ', $anosAsociados);
                }
                // Si no tiene años específicos, la campaña aplica para todos los años
            }

            if ($aplicaCampana) {
                Log::info("[AgendarCita] Campaña {$campana->id} ({$campana->title}) APLICA para el vehículo y local seleccionados");
            } else {
                Log::info("[AgendarCita] Campaña {$campana->id} ({$campana->title}) NO APLICA. Razones: " . implode('; ', $razonesExclusion));
            }

            return $aplicaCampana;
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al verificar si campaña aplica: ' . $e->getMessage());

            // En caso de error, mostrar la campaña
            return true;
        }
    }

    // Método para volver a la página de vehículos
    public function volverAVehiculos(): void
    {
        $this->redirect(Vehiculos::getUrl());
    }

    // Método para continuar al siguiente paso
    public function continuar(): void
    {
        // Verificar el estado de la variable vehiculo antes de continuar
        Log::info("[AgendarCita] Estado de la variable vehiculo antes de continuar (paso {$this->pasoActual}):", $this->vehiculo ?? ['vehiculo' => 'null']);

        // Validación básica para el paso 1
        if ($this->pasoActual == 1) {
            // Validar todos los datos del cliente antes de continuar
            if (!$this->validarDatosCliente()) {
                return; // No continuar si la validación falla
            }
            
            // Si la validación pasa, avanzar al siguiente paso
            $this->pasoActual++;
        }
        // En el paso 2, guardamos la cita y avanzamos al paso 3
        elseif ($this->pasoActual == 2) {
            // Guardar la cita
            $this->guardarCita();
            // Avanzar al paso 3 (confirmación)
            $this->pasoActual++;
        }

        // Verificar el estado de la variable vehiculo después de continuar
        Log::info("[AgendarCita] Estado de la variable vehiculo después de continuar (paso {$this->pasoActual}):", $this->vehiculo ?? ['vehiculo' => 'null']);
    }

    // Método para volver al paso anterior
    public function volver(): void
    {
        if ($this->pasoActual > 1) {
            $this->pasoActual--;
        } else {
            // Si estamos en el primer paso, volver a la página de vehículos
            $this->volverAVehiculos();
        }
    }

    /**
     * Verificar el estado del job de C4C (llamado por polling Ajax)
     */
    public function checkJobStatus(): void
    {
        if (! $this->citaJobId) {
            return;
        }

        $jobData = Cache::get("cita_job_{$this->citaJobId}");

        if ($jobData) {
            $newStatus = $jobData['status'] ?? 'idle';
            $newProgress = $jobData['progress'] ?? 0;
            $newMessage = $jobData['message'] ?? '';

            // Solo actualizar si hay cambios
            if ($newStatus !== $this->citaStatus || $newProgress !== $this->citaProgress) {
                $this->citaStatus = $newStatus;
                $this->citaProgress = $newProgress;
                $this->citaMessage = $newMessage;

                Log::info('[AgendarCita] Job status actualizado', [
                    'job_id' => $this->citaJobId,
                    'status' => $this->citaStatus,
                    'progress' => $this->citaProgress,
                    'message' => $this->citaMessage,
                ]);

                // Si se completó exitosamente
                if ($this->citaStatus === 'completed') {
                    $this->appointmentNumber = $jobData['appointment_number'] ?? null;
                    $this->citaAgendada = true;
                    $this->pasoActual = 3; // Ir al paso de confirmación

                    \Filament\Notifications\Notification::make()
                        ->title('¡Cita Confirmada!')
                        ->body('Tu cita ha sido agendada exitosamente.')
                        ->success()
                        ->send();

                    // Detener el polling
                    $this->dispatch('stop-polling');
                }

                // Si falló
                elseif ($this->citaStatus === 'failed') {
                    \Filament\Notifications\Notification::make()
                        ->title('Error al Agendar Cita')
                        ->body($this->citaMessage)
                        ->danger()
                        ->send();

                    // Detener el polling
                    $this->dispatch('stop-polling');

                    // NO resetear estado automáticamente para que el usuario vea el error
                }
            }
        }
    }

    /**
     * Resetear el estado de la cita para intentar de nuevo
     */
    public function resetearEstadoCita(): void
    {
        $this->citaStatus = 'idle';
        $this->citaProgress = 0;
        $this->citaMessage = '';
        $this->citaJobId = null;
        $this->appointmentNumber = null;

        Log::info('[AgendarCita] Estado de cita reseteado para nuevo intento');
    }

    // Método para guardar la cita
    protected function guardarCita(): void
    {
        try {
            Log::info('💾 [AgendarCita::guardarCita] ========== INICIANDO GUARDADO ==========');
            Log::info('[AgendarCita::guardarCita] Verificando modo edición', [
                'edit_mode' => $this->editMode,
                'original_uuid' => $this->originalUuid ?? 'N/A'
            ]);

            // DETECTAR MODO EDICIÓN - si estamos editando, usar reprogramación
            if ($this->editMode) {
                Log::info('🔄 [AgendarCita::guardarCita] MODO EDICIÓN DETECTADO - Redirigiendo a reprogramarCita()');
                $this->reprogramarCita();
                Log::info('🔄 [AgendarCita::guardarCita] Reprogramación completada - terminando guardarCita()');
                return;
            }

            Log::info('📝 [AgendarCita::guardarCita] MODO CREACIÓN - Continúa con flujo normal');

            // Validar datos básicos
            if (empty($this->nombreCliente) || empty($this->apellidoCliente) || empty($this->localSeleccionado)) {
                \Filament\Notifications\Notification::make()
                    ->title('Error al Agendar Cita')
                    ->body('Por favor complete todos los campos obligatorios.')
                    ->danger()
                    ->send();

                return;
            }

            // Obtener el vehículo
            $vehicle = Vehicle::where('license_plate', $this->vehiculo['placa'])->first();

            if (! $vehicle) {
                \Filament\Notifications\Notification::make()
                    ->title('Error al Agendar Cita')
                    ->body('No se encontró el vehículo seleccionado.')
                    ->danger()
                    ->send();

                return;
            }

            // Obtener el local seleccionado
            $localSeleccionado = null;
            if (! empty($this->localSeleccionado)) {
                $localSeleccionado = Local::where('code', $this->localSeleccionado)->first();
            }

            if (! $localSeleccionado) {
                \Filament\Notifications\Notification::make()
                    ->title('Error al Agendar Cita')
                    ->body('No se encontró el local seleccionado.')
                    ->danger()
                    ->send();

                return;
            }

            Log::info("[AgendarCita] Local seleccionado para la cita: {$localSeleccionado->name} (ID: {$localSeleccionado->id})");

            // Convertir la fecha de formato DD/MM/YYYY a YYYY-MM-DD
            $fechaPartes = explode('/', $this->fechaSeleccionada);
            $fechaFormateada = $fechaPartes[2] . '-' . $fechaPartes[1] . '-' . $fechaPartes[0];

            // Convertir la hora de formato "11:15 AM" a formato "HH:MM:SS"
            $horaFormateada = date('H:i', strtotime($this->horaSeleccionada));

            // Obtener el usuario autenticado
            $user = Auth::user();

            // 🚀 **NUEVA IMPLEMENTACIÓN CON JOBS - SIN TIMEOUT** 🚀
            Log::info('[AgendarCita] Iniciando proceso asíncrono de cita...');

            // Generar ID único para el job
            $this->citaJobId = (string) Str::uuid();

            // ✅ VALIDACIÓN DE MAPEO ORGANIZACIONAL
            if (!$vehicle->brand_code) {
                Log::error('❌ Vehículo sin brand_code', [
                    'vehicle_id' => $vehicle->id,
                    'license_plate' => $vehicle->license_plate
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Error al Agendar Cita')
                    ->body('El vehículo no tiene código de marca configurado. Contacte al administrador.')
                    ->danger()
                    ->send();
                return;
            }

            // Verificar que existe mapeo organizacional
            $mappingExists = CenterOrganizationMapping::mappingExists(
                $localSeleccionado->code,
                $vehicle->brand_code
            );

            if (!$mappingExists) {
                Log::error('❌ No existe mapeo organizacional', [
                    'center_code' => $localSeleccionado->code,
                    'brand_code' => $vehicle->brand_code
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Error al Agendar Cita')
                    ->body('No existe configuración organizacional para este centro y marca. Contacte al administrador.')
                    ->danger()
                    ->send();
                return;
            }

            Log::info('✅ Validación de mapeo organizacional exitosa', [
                'center_code' => $localSeleccionado->code,
                'brand_code' => $vehicle->brand_code
            ]);

            // **PASO 1: CREAR APPOINTMENT EN BD PRIMERO** 💾
            $appointment = new Appointment;
            $appointment->appointment_number = 'CITA-' . date('Ymd') . '-' . strtoupper(Str::random(5));
            $appointment->vehicle_id = $vehicle->id;
            $appointment->premise_id = $localSeleccionado->id;
            $appointment->customer_ruc = $user ? $user->document_number : '20605414410';
            $appointment->customer_name = $this->nombreCliente;
            $appointment->customer_last_name = $this->apellidoCliente;
            $appointment->customer_email = $this->emailCliente;
            $appointment->customer_phone = $this->celularCliente;
            $appointment->appointment_date = $fechaFormateada;
            $appointment->appointment_time = $horaFormateada;

            // ✅ NUEVOS CAMPOS PARA MAPEO ORGANIZACIONAL
            $appointment->vehicle_brand_code = $vehicle->brand_code; // Z01, Z02, Z03
            $appointment->center_code = $localSeleccionado->code; // M013, L013, etc.

            // ✅ OBTENER PACKAGE_ID ANTES DE ASIGNAR
            if (!$this->paqueteId && ($this->tipoMantenimiento || !empty($this->serviciosAdicionales) || !empty($this->campanaSeleccionada))) {
                $this->obtenerPaqueteId();
            }

            // ✅ DETECTAR CLIENTE COMODÍN ANTES DE ASIGNAR PACKAGE_ID
            $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

            // 🔍 DEBUG: Log para verificar detección
            Log::info('🔍 [AgendarCita] Detección cliente wildcard', [
                'user_c4c_id' => $user ? $user->c4c_internal_id : 'NO_USER',
                'is_wildcard' => $isWildcardClient,
                'paquete_id' => $this->paqueteId,
                'customer_ruc' => $user ? $user->document_number : 'NO_USER'
            ]);

            // Solo asignar package_id si NO es cliente comodín
            $appointment->package_id = $isWildcardClient ? null : $this->paqueteId;

            // 🔍 DEBUG: Log del resultado
            Log::info('🔍 [AgendarCita] Package ID asignado', [
                'is_wildcard' => $isWildcardClient,
                'assigned_package_id' => $appointment->package_id,
                'original_paquete_id' => $this->paqueteId
            ]);
            $appointment->vehicle_plate = $vehicle->license_plate; // Para referencia rápida

            // Determinar el service_mode basado en los servicios seleccionados
            $serviceModes = [];
            if (in_array('Mantenimiento periódico', $this->serviciosSeleccionados)) {
                $serviceModes[] = 'Mantenimiento periódico';
            }
            if (in_array('Campañas / otros', $this->serviciosSeleccionados)) {
                $serviceModes[] = 'Campañas / otros';
            }
            if (in_array('Reparación', $this->serviciosSeleccionados)) {
                $serviceModes[] = 'Reparación';
            }
            if (in_array('Llamado a revisión', $this->serviciosSeleccionados)) {
                $serviceModes[] = 'Llamado a revisión';
            }

            // ✅ NUEVO: Agregar modalidad Express si está seleccionada
            if ($this->modalidadServicio === 'Mantenimiento Express') {
                $serviceModes[] = 'express';
            }
            $appointment->service_mode = implode(', ', $serviceModes);
            $appointment->maintenance_type = $this->tipoMantenimiento;
            $appointment->comments = $this->comentarios;
            
            // ✅ GUARDAR SELECCIONES EN CAMPO JSON:
            // - Para clientes WILDCARD: Siempre guardar si tienen selecciones
            // - Para clientes NORMALES: Solo guardar si NO hay maintenance_type Y tienen selecciones
            $shouldSaveWildcardSelections = false;
            
            if ($isWildcardClient) {
                // Clientes wildcard: siempre guardar si tienen selecciones
                $shouldSaveWildcardSelections = !empty($this->serviciosAdicionales) || !empty($this->campanaSeleccionada);
            } else {
                // Clientes normales: solo guardar si NO hay mantenimiento Y tienen selecciones
                $shouldSaveWildcardSelections = empty($this->tipoMantenimiento) && 
                                               (!empty($this->serviciosAdicionales) || !empty($this->campanaSeleccionada));
            }
            
            if ($shouldSaveWildcardSelections) {
                $wildcardSelections = [];
                
                // Servicios adicionales seleccionados
                if (!empty($this->serviciosAdicionales)) {
                    $serviciosNombres = [];
                    foreach ($this->serviciosAdicionales as $servicioId) {
                        $id = str_replace('servicio_', '', $servicioId);
                        $servicio = \App\Models\AdditionalService::find($id);
                        if ($servicio) {
                            $serviciosNombres[] = $servicio->name;
                        }
                    }
                    $wildcardSelections['servicios_adicionales'] = $serviciosNombres;
                }
                
                // Campañas seleccionadas
                if (!empty($this->campanaSeleccionada)) {
                    $campana = collect($this->campanasDisponibles)->firstWhere('id', $this->campanaSeleccionada);
                    if ($campana) {
                        $wildcardSelections['campanas'] = [$campana['titulo']];
                    }
                }
                
                $appointment->wildcard_selections = !empty($wildcardSelections) ? json_encode($wildcardSelections) : null;
                
                // 🔍 DEBUG: Log para verificar guardado
                Log::info('🔍 [AgendarCita] Guardando wildcard_selections', [
                    'appointment_id' => $appointment->id ?? 'NEW',
                    'is_wildcard_client' => $isWildcardClient,
                    'maintenance_type' => $this->tipoMantenimiento ?? 'EMPTY',
                    'should_save' => $shouldSaveWildcardSelections,
                    'wildcard_selections' => $wildcardSelections
                ]);
            }
            
            $appointment->status = 'pending'; // Pendiente hasta que C4C confirme
            $appointment->is_synced = false;

            $appointment->save();

            Log::info("[AgendarCita] Appointment creado en BD con ID: {$appointment->id}");

            // **PASO 2: PREPARAR DATOS PARA C4C** 📋
            $fechaHoraInicio = Carbon::createFromFormat('Y-m-d H:i', $fechaFormateada . ' ' . $horaFormateada);
            $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes(45); // 45 minutos por defecto

            $citaData = [
                'customer_id' => $user->c4c_internal_id ?? '1270002726', // Cliente de prueba si no tiene C4C ID
                'employee_id' => '1740', // ID del asesor por defecto
                'start_date' => $fechaHoraInicio->format('Y-m-d H:i'),
                'end_date' => $fechaHoraFin->format('Y-m-d H:i'),
                'center_id' => $localSeleccionado->code,
                'vehicle_plate' => $vehicle->license_plate,
                'customer_name' => $this->nombreCliente . ' ' . $this->apellidoCliente,
                'notes' => $this->generarComentarioCompleto() ?: null,
                'express' => strpos($appointment->service_mode, 'express') !== false ? 'true' : 'false',
            ];

            $appointmentData = [
                'appointment_number' => $appointment->appointment_number,
                'servicios_adicionales' => $this->serviciosAdicionales,
                'campanas_disponibles' => $this->campanasDisponibles ?? [],
            ];

            // **PASO 3: INICIALIZAR JOB STATUS** ⏳
            Cache::put("cita_job_{$this->citaJobId}", [
                'status' => 'queued',
                'progress' => 0,
                'message' => 'Preparando envío a C4C...',
                'updated_at' => now(),
            ], 600); // 10 minutos

            // **PASO 4: DESPACHAR JOB EN BACKGROUND** 🚀
            EnviarCitaC4CJob::dispatch($citaData, $appointmentData, $this->citaJobId, $appointment->id);

            ProcessAppointmentAfterCreationJob::dispatch($appointment->id)
                ->delay(now()->addMinutes(1)); // Delay para que la cita se procese primero

            // **PASO 5: ACTUALIZAR UI INMEDIATAMENTE** ⚡
            $this->citaStatus = 'processing';
            $this->citaProgress = 0;
            $this->citaMessage = 'Enviando cita a C4C...';

            Log::info('[AgendarCita] Job despachado exitosamente', [
                'job_id' => $this->citaJobId,
                'appointment_id' => $appointment->id,
            ]);

            // **PASO 6: NOTIFICAR AL USUARIO** ✅
            \Filament\Notifications\Notification::make()
                ->title('Procesando Cita')
                ->body('Tu cita está siendo procesada. Por favor espera...')
                ->info()
                ->send();

            // **PASO 7: INICIAR POLLING** 🔄
            $this->dispatch('start-polling', jobId: $this->citaJobId);

            // **GUARDAR SERVICIOS ADICIONALES** (mantenemos esta lógica)
            if (! empty($this->serviciosAdicionales)) {
                foreach ($this->serviciosAdicionales as $servicioAdicionalKey) {
                    // Verificar si es una campaña
                    if (strpos($servicioAdicionalKey, 'campana_') === 0) {
                        // Es una campaña, extraer el ID
                        $campanaId = substr($servicioAdicionalKey, 8);
                        Log::info("[AgendarCita] Procesando campaña con ID: {$campanaId}");

                        // Buscar la campaña en el array de campañas disponibles
                        $campanaEncontrada = null;
                        foreach ($this->campanasDisponibles as $campana) {
                            if ($campana['id'] == $campanaId) {
                                $campanaEncontrada = $campana;
                                break;
                            }
                        }

                        if ($campanaEncontrada) {
                            // Crear un servicio adicional para la campaña si no existe
                            $nombreServicio = 'Campaña: ' . $campanaEncontrada['titulo'];
                            // Generar un código único para el servicio adicional basado en el ID de la campaña
                            $codigoServicio = 'CAMP-' . str_pad($campanaId, 5, '0', STR_PAD_LEFT);

                            $additionalService = AdditionalService::firstOrCreate(
                                ['code' => $codigoServicio],
                                [
                                    'name' => $nombreServicio,
                                    'description' => $campanaEncontrada['descripcion'],
                                    'is_active' => true,
                                ]
                            );

                            // Adjuntar el servicio a la cita (evitar duplicados)
                            if (!$appointment->additionalServices()->where('additional_service_id', $additionalService->id)->exists()) {
                                $appointment->additionalServices()->attach($additionalService->id, [
                                    'notes' => "Campaña ID: {$campanaId}, Válida hasta: " . $campanaEncontrada['fecha_fin'],
                                ]);
                            }

                            Log::info("[AgendarCita] Campaña adjuntada a la cita: {$nombreServicio}");
                        } else {
                            Log::warning("[AgendarCita] No se encontró la campaña con ID: {$campanaId}");
                        }
                    } else {
                        // Procesar servicios adicionales tradicionales
                        Log::info("[AgendarCita] Procesando servicio adicional tradicional: {$servicioAdicionalKey}");
                        
                        // Extraer el ID del servicio del key (formato: 'servicio_X' donde X es el ID)
                        if (strpos($servicioAdicionalKey, 'servicio_') === 0) {
                            $servicioId = substr($servicioAdicionalKey, 9); // Remover 'servicio_'
                            
                            // Buscar el servicio adicional en la base de datos
                            $additionalService = AdditionalService::find($servicioId);
                            
                            if ($additionalService) {
                                // Crear el registro en appointment_additional_service
                                \App\Models\AppointmentAdditionalService::create([
                                    'appointment_id' => $appointment->id,
                                    'additional_service_id' => $additionalService->id,
                                    'notes' => 'Servicio adicional seleccionado durante el agendamiento',
                                    'price' => null // Se puede agregar precio después si es necesario
                                ]);
                                
                                Log::info("[AgendarCita] Servicio adicional guardado: {$additionalService->name} (ID: {$additionalService->id})");
                            } else {
                                Log::warning("[AgendarCita] No se encontró el servicio adicional con ID: {$servicioId}");
                            }
                        } else {
                            Log::warning("[AgendarCita] Formato de servicio adicional no reconocido: {$servicioAdicionalKey}");
                        }
                    }
                }
            }

            // **EMAIL SE ENVIARÁ DESPUÉS DEL ÉXITO EN C4C** 📧
            // El email de confirmación se enviará desde EnviarCitaC4CJob cuando la sincronización sea exitosa
            Log::info('📧 [AgendarCita] Email de confirmación se enviará después del éxito en C4C', [
                'appointment_id' => $appointment->id,
                'customer_email' => $this->emailCliente
            ]);

        } catch (\Exception $e) {
            // Registrar el error
            Log::error('[AgendarCita] Error al iniciar proceso de cita: ' . $e->getMessage());

            // Mostrar notificación de error
            \Filament\Notifications\Notification::make()
                ->title('Error al Procesar Cita')
                ->body('Ocurrió un error al procesar la cita: ' . $e->getMessage())
                ->danger()
                ->send();

            // Resetear estado
            $this->citaStatus = 'idle';
            $this->citaProgress = 0;
            $this->citaJobId = null;
        }
    }

    // Método para finalizar el proceso de agendamiento
    public function finalizarAgendamiento(): void
    {
        // VALIDAR DATOS DEL CLIENTE PRIMERO
        if (empty(trim($this->nombreCliente)) || empty(trim($this->apellidoCliente)) || 
            empty(trim($this->emailCliente)) || empty(trim($this->celularCliente))) {
            
            \Filament\Notifications\Notification::make()
                ->title('Datos incompletos')
                ->body('Por favor, completa todos los campos en la sección "Revisa tus datos" antes de continuar.')
                ->warning()
                ->duration(5000)
                ->send();
            
            Log::warning('[AgendarCita] Intento de continuar con datos del cliente incompletos:', [
                'nombreCliente' => $this->nombreCliente,
                'apellidoCliente' => $this->apellidoCliente,
                'emailCliente' => $this->emailCliente,
                'celularCliente' => $this->celularCliente,
            ]);
            
            return; // No continuar si los datos están incompletos
        }

        // Validar formato de email
        if (!filter_var($this->emailCliente, FILTER_VALIDATE_EMAIL)) {
            \Filament\Notifications\Notification::make()
                ->title('Email inválido')
                ->body('Por favor, ingresa un email válido en la sección "Revisa tus datos".')
                ->warning()
                ->duration(5000)
                ->send();
            
            Log::warning('[AgendarCita] Email inválido proporcionado:', [
                'emailCliente' => $this->emailCliente,
            ]);
            
            return; // No continuar si el email es inválido
        }

        // Verificar el estado de la variable vehiculo antes de avanzar
        Log::info('[AgendarCita] Estado de la variable vehiculo antes de avanzar al paso 2:', $this->vehiculo ?? ['vehiculo' => 'null']);

        // Verificar que se haya seleccionado un local
        if (empty($this->localSeleccionado)) {
            // Si no se ha seleccionado un local, seleccionar el primero por defecto
            if (! empty($this->locales)) {
                $this->localSeleccionado = array_key_first($this->locales);
                Log::info("[AgendarCita] Seleccionando local por defecto: {$this->localSeleccionado}");
            }
        }

        // Verificar que se haya seleccionado una fecha
        if (empty($this->fechaSeleccionada)) {
            // Si no se ha seleccionado una fecha, usar la fecha actual
            $this->fechaSeleccionada = date('d/m/Y');
            Log::info("[AgendarCita] Seleccionando fecha por defecto: {$this->fechaSeleccionada}");
        }

        // Validar que la fecha seleccionada esté dentro del rango permitido
        try {
            $fechaCarbon = Carbon::createFromFormat('d/m/Y', $this->fechaSeleccionada);
            if (!$this->validarIntervaloReserva($fechaCarbon)) {
                Log::warning('[AgendarCita] Intento de continuar con fecha fuera del rango permitido:', [
                    'fechaSeleccionada' => $this->fechaSeleccionada,
                ]);
                return; // No continuar si la fecha no es válida
            }
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al validar fecha seleccionada: ' . $e->getMessage());
            return;
        }

        // Verificar que se haya seleccionado una hora
        if (empty($this->horaSeleccionada)) {
            \Filament\Notifications\Notification::make()
                ->title('Hora no seleccionada')
                ->body('Por favor, selecciona una hora de los horarios disponibles antes de continuar.')
                ->warning()
                ->duration(5000)
                ->send();

            Log::warning('[AgendarCita] Intento de continuar sin seleccionar hora:', [
                'fechaSeleccionada' => $this->fechaSeleccionada,
                'horariosDisponibles' => $this->horariosDisponibles,
            ]);

            return; // No continuar si no se ha seleccionado una hora
        }

        // Verificar que los datos del vehículo estén completos
        if (empty($this->vehiculo['id']) || empty($this->vehiculo['placa']) || empty($this->vehiculo['modelo'])) {
            // Intentar buscar el vehículo en la base de datos
            $vehiculoEncontrado = null;

            if (! empty($this->vehiculo['placa'])) {
                $vehiculoEncontrado = Vehicle::where('license_plate', $this->vehiculo['placa'])->first();
            }

            if (! $vehiculoEncontrado && ! empty($this->vehiculo['id'])) {
                $vehiculoEncontrado = Vehicle::where('vehicle_id', $this->vehiculo['id'])->first();
            }

            if ($vehiculoEncontrado) {
                // Si encontramos el vehículo en la base de datos, usamos sus datos
                $this->vehiculo = [
                    'id' => $vehiculoEncontrado->vehicle_id,
                    'placa' => $vehiculoEncontrado->license_plate,
                    'modelo' => $vehiculoEncontrado->model,
                    'anio' => $vehiculoEncontrado->year,
                    'marca' => $vehiculoEncontrado->brand_name,
                ];

                Log::info('[AgendarCita] Vehículo actualizado desde la base de datos:', $this->vehiculo);
            } else {
                // Si no encontramos el vehículo, asegurarnos de que al menos tenga valores predeterminados
                if (empty($this->vehiculo['modelo'])) {
                    $this->vehiculo['modelo'] = 'No especificado';
                }
                if (empty($this->vehiculo['placa'])) {
                    $this->vehiculo['placa'] = 'No especificado';
                }
                if (empty($this->vehiculo['anio'])) {
                    $this->vehiculo['anio'] = 'No especificado';
                }
                if (empty($this->vehiculo['marca'])) {
                    $this->vehiculo['marca'] = 'No especificado';
                }

                Log::info('[AgendarCita] Vehículo actualizado con valores predeterminados:', $this->vehiculo);
            }
        }

        // Registrar los valores seleccionados
        Log::info('[AgendarCita] Valores seleccionados:', [
            'vehiculo' => $this->vehiculo,
            'localSeleccionado' => $this->localSeleccionado,
            'fechaSeleccionada' => $this->fechaSeleccionada,
            'horaSeleccionada' => $this->horaSeleccionada,
            'serviciosSeleccionados' => $this->serviciosSeleccionados,
            'tipoMantenimiento' => $this->tipoMantenimiento,
            'modalidadServicio' => $this->modalidadServicio,
            'serviciosAdicionales' => $this->serviciosAdicionales,
            'comentarios' => $this->comentarios,
        ]);

        // Avanzar al paso 2 (resumen)
        $this->pasoActual = 2;

        // Verificar el estado de la variable vehiculo después de avanzar
        Log::info('[AgendarCita] Estado de la variable vehiculo después de avanzar al paso 2:', $this->vehiculo ?? ['vehiculo' => 'null']);
    }

    // Método para cerrar y mostrar el modal de pop-ups
    public function cerrarYVolverACitas(): void
    {
        // Solo mostrar el modal de pop-ups si la cita fue agendada exitosamente
        if ($this->citaAgendada && $this->citaStatus === 'completed' && ! empty($this->popupsDisponibles)) {
            $this->mostrarModalPopups = true;
            Log::info('[AgendarCita] Mostrando modal de pop-ups con ' . count($this->popupsDisponibles) . ' opciones');
        } else {
            Log::info('[AgendarCita] No se muestra modal de pop-ups - Cita no exitosa o sin pop-ups disponibles', [
                'citaAgendada' => $this->citaAgendada,
                'citaStatus' => $this->citaStatus,
                'popupsDisponibles' => count($this->popupsDisponibles)
            ]);
            // Si no hay pop-ups disponibles o la cita no fue exitosa, redirigir a la página de vehículos
            $this->redirect(Vehiculos::getUrl());
        }
    }

    // Método para cancelar desde el modal de éxito y volver a vehículos con pestañas
    public function cancelarYVolverAVehiculos(): void
    {
        Log::info('[AgendarCita] Cancelando desde modal de éxito y volviendo a vehículos con pestañas');

        // Resetear el estado de la cita
        $this->citaStatus = 'idle';
        $this->citaProgress = 0;
        $this->citaMessage = '';
        $this->citaJobId = null;
        $this->appointmentNumber = null;

        // Redirigir específicamente a la página de vehículos con pestañas
        $this->redirect(Vehiculos::getUrl());
    }

    // Método para continuar después del éxito (mostrar pop-ups o ir a vehículos)
    public function continuarDespuesDeExito(): void
    {
        Log::info('[AgendarCita] Continuando después del éxito del modal');
        
        // Solo mostrar el modal de pop-ups si la cita fue agendada exitosamente
        if ($this->citaAgendada && $this->citaStatus === 'completed' && ! empty($this->popupsDisponibles)) {
            $this->mostrarModalPopups = true;
            Log::info('[AgendarCita] Mostrando modal de pop-ups con ' . count($this->popupsDisponibles) . ' opciones');
        } else {
            Log::info('[AgendarCita] No se muestra modal de pop-ups - Cita no exitosa o sin pop-ups disponibles', [
                'citaAgendada' => $this->citaAgendada,
                'citaStatus' => $this->citaStatus,
                'popupsDisponibles' => count($this->popupsDisponibles)
            ]);
            // Si no hay pop-ups disponibles o la cita no fue exitosa, redirigir a la página de vehículos con pestañas
            $this->redirect(Vehiculos::getUrl());
        }
        
        // Resetear el estado después de procesar
        $this->citaStatus = 'idle';
    }

    /**
     * Método para seleccionar/deseleccionar un pop-up
     */
    public function togglePopup(int $popupId): void
    {
        // Verificar si el pop-up ya está seleccionado
        $index = array_search($popupId, $this->popupsSeleccionados);

        if ($index !== false) {
            // Si ya está seleccionado, quitarlo
            unset($this->popupsSeleccionados[$index]);
            $this->popupsSeleccionados = array_values($this->popupsSeleccionados); // Reindexar el array
            Log::info("[AgendarCita] Pop-up {$popupId} deseleccionado");
        } else {
            // Si no está seleccionado, agregarlo
            $this->popupsSeleccionados[] = $popupId;
            Log::info("[AgendarCita] Pop-up {$popupId} seleccionado");
        }
    }

    /**
     * Método para solicitar información sobre los pop-ups seleccionados
     */
    public function solicitarInformacion(): void
    {
        // Verificar si hay pop-ups seleccionados
        if (empty($this->popupsSeleccionados)) {
            // Si no hay pop-ups seleccionados, mostrar notificación
            \Filament\Notifications\Notification::make()
                ->title('Sin selección')
                ->body('No has seleccionado ningún servicio adicional.')
                ->warning()
                ->send();

            // Cerrar el modal de pop-ups
            $this->mostrarModalPopups = false;

            return;
        }

        // Cerrar el modal de pop-ups y mostrar el modal de resumen
        $this->mostrarModalPopups = false;
        $this->mostrarModalResumenPopups = true;

        Log::info('[AgendarCita] Mostrando resumen de pop-ups seleccionados: ' . implode(', ', $this->popupsSeleccionados));
    }

    /**
     * Enviar correos electrónicos para cada pop-up seleccionado
     */
    protected function enviarCorreosInformacion(): void
    {
        try {
            // Obtener datos del usuario autenticado
            $user = Auth::user();

            if (! $user) {
                Log::error('[AgendarCita] No hay usuario autenticado para enviar correos');

                return;
            }

            // Preparar datos del usuario
            $datosUsuario = [
                'nombres' => $this->nombreCliente,
                'apellidos' => $this->apellidoCliente,
                'email' => $this->emailCliente,
                'celular' => $this->celularCliente,
                'dni' => $user->document_number ?? '',
                'placa' => $this->vehiculo['placa'] ?? '',
            ];

            Log::info('[AgendarCita] Iniciando envío de correos para pop-ups seleccionados', [
                'popups_seleccionados' => $this->popupsSeleccionados,
                'datos_usuario' => $datosUsuario,
            ]);

            $correosEnviados = 0;
            $errores = [];

            // Enviar un correo por cada pop-up seleccionado
            foreach ($this->popupsSeleccionados as $popupId) {
                // Buscar el pop-up en los disponibles
                $popup = collect($this->popupsDisponibles)->firstWhere('id', $popupId);

                if (! $popup) {
                    Log::warning("[AgendarCita] Pop-up con ID {$popupId} no encontrado en disponibles");

                    continue;
                }

                // Extraer el correo electrónico del campo url_wp
                $correoDestino = $this->extraerCorreoDeUrlWp($popup['url_wp']);

                if (! $correoDestino) {
                    Log::warning("[AgendarCita] No se pudo extraer correo válido de url_wp: {$popup['url_wp']}");
                    $errores[] = "No se pudo enviar correo para {$popup['nombre']} - correo no válido";

                    continue;
                }

                try {
                    Log::info("[AgendarCita] Intentando enviar correo para {$popup['nombre']} a {$correoDestino}");
                    Log::info('[AgendarCita] Datos del usuario: ' . json_encode($datosUsuario));

                    // Verificar configuración de correo
                    $mailConfig = config('mail');
                    Log::info('[AgendarCita] Configuración de correo - Driver: ' . $mailConfig['default']);
                    Log::info('[AgendarCita] Configuración SMTP - Host: ' . config('mail.mailers.smtp.host'));

                    // Enviar el correo
                    Mail::to($correoDestino)
                        ->send(new SolicitudInformacionPopup($datosUsuario, $popup['nombre']));

                    $correosEnviados++;
                    Log::info("[AgendarCita] ✅ Correo enviado exitosamente para {$popup['nombre']} a {$correoDestino}");
                } catch (\Exception $e) {
                    Log::error("[AgendarCita] ❌ Error al enviar correo para {$popup['nombre']}: " . $e->getMessage());
                    Log::error('[AgendarCita] Stack trace: ' . $e->getTraceAsString());
                    $errores[] = "Error al enviar correo para {$popup['nombre']}: " . $e->getMessage();
                }
            }

            // Mostrar notificación según el resultado
            if ($correosEnviados > 0) {
                $mensaje = $correosEnviados === 1
                    ? 'Se ha enviado 1 solicitud de información por correo electrónico.'
                    : "Se han enviado {$correosEnviados} solicitudes de información por correo electrónico.";

                if (! empty($errores)) {
                    $mensaje .= ' Algunos envíos fallaron.';
                }

                \Filament\Notifications\Notification::make()
                    ->title('Solicitudes Enviadas')
                    ->body($mensaje)
                    ->success()
                    ->send();
            } else {
                \Filament\Notifications\Notification::make()
                    ->title('Error en el Envío')
                    ->body('No se pudo enviar ninguna solicitud de información. Verifique la configuración de correos.')
                    ->danger()
                    ->send();
            }

            Log::info("[AgendarCita] Proceso de envío completado. Enviados: {$correosEnviados}, Errores: " . count($errores));
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error general al enviar correos de información: ' . $e->getMessage());

            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al procesar las solicitudes de información.')
                ->danger()
                ->send();
        }
    }

    /**
     * Extraer correo electrónico del campo url_wp
     */
    protected function extraerCorreoDeUrlWp(?string $urlWp): ?string
    {
        if (empty($urlWp)) {
            Log::info('[AgendarCita] url_wp está vacío');

            return null;
        }

        Log::info("[AgendarCita] Procesando url_wp: {$urlWp}");

        // Si ya es un correo electrónico válido, devolverlo
        if (filter_var($urlWp, FILTER_VALIDATE_EMAIL)) {
            Log::info("[AgendarCita] url_wp es un correo válido: {$urlWp}");

            return $urlWp;
        }

        Log::error("[AgendarCita] El valor en url_wp no es un correo válido: {$urlWp}");

        return null;
    }

    /**
     * Método para cerrar el modal de resumen y volver a la página de vehículos
     */
    public function cerrarResumen(): void
    {
        // Cerrar el modal de resumen
        $this->mostrarModalResumenPopups = false;

        // Guardar los pop-ups seleccionados en la base de datos o enviar notificación
        $this->guardarPopupsSeleccionados();

        // Redirigir a la página de vehículos
        $this->redirect(Vehiculos::getUrl());
    }

    /**
     * Método para guardar los pop-ups seleccionados
     */
    protected function guardarPopupsSeleccionados(): void
    {
        try {
            // Si no hay pop-ups seleccionados, no hacer nada
            if (empty($this->popupsSeleccionados)) {
                Log::info('[AgendarCita] No hay pop-ups seleccionados para guardar');

                return;
            }

            // Obtener los pop-ups seleccionados
            $popupsSeleccionados = [];
            foreach ($this->popupsDisponibles as $popup) {
                if (in_array($popup['id'], $this->popupsSeleccionados)) {
                    $popupsSeleccionados[] = $popup;
                }
            }

            Log::info('[AgendarCita] Pop-ups seleccionados guardados: ' . json_encode($popupsSeleccionados));

            // Enviar correos electrónicos para cada pop-up seleccionado
            $this->enviarCorreosInformacion();

            // Mostrar notificación de éxito
            \Filament\Notifications\Notification::make()
                ->title('Solicitud Enviada')
                ->body('Tu solicitud de información ha sido enviada. Pronto te contactaremos.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al guardar pop-ups seleccionados: ' . $e->getMessage());

            // Mostrar notificación de error
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.')
                ->danger()
                ->send();
        }
    }

    /**
     * Genera el calendario para el mes y año actual
     */
    public function generarCalendario(): void
    {
        // Crear una fecha para el primer día del mes actual
        $primerDia = Carbon::createFromDate($this->anoActual, $this->mesActual, 1);

        // Obtener el día de la semana del primer día (0 = domingo, 1 = lunes, ..., 6 = sábado)
        $diaSemana = $primerDia->dayOfWeek;

        // Ajustar para que la semana comience en lunes (0 = lunes, ..., 6 = domingo)
        $diaSemana = $diaSemana == 0 ? 6 : $diaSemana - 1;

        // Obtener el número de días en el mes actual
        $diasEnMes = $primerDia->daysInMonth;

        // Crear un array para almacenar los días del calendario
        $diasCalendario = [];

        // Agregar los días del mes anterior
        $mesAnterior = $primerDia->copy()->subMonth();
        $diasEnMesAnterior = $mesAnterior->daysInMonth;

        for ($i = 0; $i < $diaSemana; $i++) {
            $dia = $diasEnMesAnterior - $diaSemana + $i + 1;
            $fecha = $mesAnterior->copy()->setDay($dia);

            $diasCalendario[] = [
                'dia' => $dia,
                'mes' => $mesAnterior->month,
                'ano' => $mesAnterior->year,
                'esActual' => false,
                'fecha' => $fecha->format('d/m/Y'),
                'disponible' => false, // Los días del mes anterior no están disponibles
                'esPasado' => $fecha->isPast(),
                'esHoy' => $fecha->isToday(),
            ];
        }

        // Agregar los días del mes actual
        $fechaActual = Carbon::now();
        $fechaHoy = Carbon::today();

        for ($dia = 1; $dia <= $diasEnMes; $dia++) {
            $fecha = Carbon::createFromDate($this->anoActual, $this->mesActual, $dia)->startOfDay();

            // Verificar si la fecha es pasada o es hoy (ambos no disponibles)
            $esPasado = $fecha->lt($fechaHoy);
            $esHoy = $fecha->isSameDay($fechaHoy);

            // Verificar si hay bloqueos para esta fecha y local
            // Solo está disponible si no es pasado, no es hoy, y no tiene bloqueos
            $disponible = ! $esPasado && ! $esHoy && $this->verificarDisponibilidadFecha($fecha);

            $diasCalendario[] = [
                'dia' => $dia,
                'mes' => $this->mesActual,
                'ano' => $this->anoActual,
                'esActual' => true,
                'fecha' => $fecha->format('d/m/Y'),
                'disponible' => $disponible,
                'esPasado' => $esPasado,
                'esHoy' => $esHoy,
                'seleccionado' => $fecha->format('d/m/Y') === $this->fechaSeleccionada,
            ];
        }

        // Agregar los días del mes siguiente para completar la última semana
        $totalDias = count($diasCalendario);
        $diasRestantes = 42 - $totalDias; // 6 semanas x 7 días = 42 días en total

        $mesSiguiente = $primerDia->copy()->addMonth();

        for ($dia = 1; $dia <= $diasRestantes; $dia++) {
            $fecha = $mesSiguiente->copy()->setDay($dia);

            $diasCalendario[] = [
                'dia' => $dia,
                'mes' => $mesSiguiente->month,
                'ano' => $mesSiguiente->year,
                'esActual' => false,
                'fecha' => $fecha->format('d/m/Y'),
                'disponible' => false, // Los días del mes siguiente no están disponibles
                'esPasado' => false,
                'esHoy' => false,
            ];
        }

        $this->diasCalendario = $diasCalendario;

        // Si hay una fecha seleccionada, cargar los horarios disponibles
        if (! empty($this->fechaSeleccionada)) {
            $this->cargarHorariosDisponibles();
        }
    }

    /**
     * Verifica si una fecha está disponible (no tiene bloqueos completos)
     */
    private function verificarDisponibilidadFecha(Carbon $fecha): bool
    {
        // Verificar si la fecha está dentro del rango permitido por los intervalos
        if (!$this->validarIntervaloReserva($fecha)) {
            return false;
        }

        // Si la fecha es hoy o anterior, no está disponible
        if ($fecha->startOfDay()->lte(Carbon::today()) || $fecha->isSameDay(Carbon::today())) {
            return false;
        }

        // Si no hay local seleccionado, no podemos verificar disponibilidad
        if (empty($this->localSeleccionado) || empty($this->locales[$this->localSeleccionado]['id'])) {
            return true;
        }

        $localId = $this->locales[$this->localSeleccionado]['id'];
        $fechaStr = $fecha->format('Y-m-d');

        // ✅ CACHÉ: Optimizar consulta de bloqueos completos
        $bloqueoCompletoCacheKey = "bloqueo_completo:{$localId}:{$fechaStr}";
        $bloqueoCompleto = Cache::remember($bloqueoCompletoCacheKey, 300, function() use ($localId, $fechaStr) {
            return Bloqueo::where('premises', $localId)
                ->where('start_date', '<=', $fechaStr)
                ->where('end_date', '>=', $fechaStr)
                ->where('all_day', true)
                ->exists();
        });

        // Depuración detallada de la consulta de bloqueos completos
        $queryBloqueoCompleto = Bloqueo::where('premises', $localId)
            ->where('start_date', '<=', $fechaStr)
            ->where('end_date', '>=', $fechaStr)
            ->where('all_day', true)
            ->toSql();
        // Si hay un bloqueo completo, la fecha no está disponible
        if ($bloqueoCompleto) {
            Log::info("[AgendarCita] Fecha {$fechaStr} bloqueada completamente para local ID: {$localId}");

            return false;
        }

        // ✅ CACHÉ: Optimizar consulta de bloqueos parciales
        $bloqueosParcialesCacheKey = "bloqueos_parciales:{$localId}:{$fechaStr}";
        $bloqueosParciales = Cache::remember($bloqueosParcialesCacheKey, 300, function() use ($localId, $fechaStr) {
            return Bloqueo::where('premises', $localId)
                ->where('start_date', '<=', $fechaStr)
                ->where('end_date', '>=', $fechaStr)
                ->where('all_day', false)
                ->get();
        });

        // Si no hay bloqueos parciales, la fecha está disponible
        if ($bloqueosParciales->isEmpty()) {
            return true;
        }

        // Verificar si todos los horarios están bloqueados
        $horariosBase = $this->obtenerHorariosBase();
        $horariosDisponibles = $horariosBase;

        foreach ($bloqueosParciales as $bloqueo) {
            $horaInicio = $bloqueo->start_time;
            $horaFin = $bloqueo->end_time;

            // Asegurarse de que las horas tengan el formato correcto (HH:MM:SS)
            if (strlen($horaInicio) <= 5) {
                $horaInicio .= ':00';
            }
            if (strlen($horaFin) <= 5) {
                $horaFin .= ':00';
            }
            // Convertir horas a objetos Carbon para comparación más precisa
            try {
                // Intentar crear objetos Carbon para las horas de inicio y fin
                try {
                    $inicioCarbon = Carbon::createFromFormat('H:i:s', $horaInicio);
                } catch (\Exception $e) {
                    // Si falla, intentar con formato H:i
                    try {
                        $inicioCarbon = Carbon::createFromFormat('H:i', $horaInicio);
                    } catch (\Exception $e2) {

                        continue;
                    }
                }

                try {
                    $finCarbon = Carbon::createFromFormat('H:i:s', $horaFin);
                } catch (\Exception $e) {
                    // Si falla, intentar con formato H:i
                    try {
                        $finCarbon = Carbon::createFromFormat('H:i', $horaFin);
                    } catch (\Exception $e2) {
                        continue;
                    }
                }

                // Convertir a strings en formato H:i:s para comparación directa
                $inicioStr = $inicioCarbon->format('H:i:s');
                $finStr = $finCarbon->format('H:i:s');

                // Guardar los horarios antes del filtrado para poder ver qué se eliminó
                $horariosAntesFiltro = $horariosDisponibles;

                // Filtrar los horarios que están dentro del rango bloqueado usando comparación directa de strings
                $horariosDisponibles = array_filter($horariosDisponibles, function ($hora) use ($inicioStr, $finStr) {
                    // Verificar si la hora está dentro del rango bloqueado
                    $dentroDelRango = ($hora >= $inicioStr && $hora <= $finStr);
                    if ($dentroDelRango) {
                        Log::info("[AgendarCita] Verificación: Hora {$hora} está dentro del rango bloqueado {$inicioStr} - {$finStr}");
                    }

                    return ! $dentroDelRango;
                });

                // Registrar los horarios que fueron eliminados
                $horariosEliminados = array_diff($horariosAntesFiltro, $horariosDisponibles);
                if (! empty($horariosEliminados)) {
                    Log::info('[AgendarCita] Horarios eliminados en verificación: ' . json_encode(array_values($horariosEliminados)));
                }
            } catch (\Exception $e) {
                Log::error('[AgendarCita] Error al procesar bloqueo en verificación: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }

        // ✅ CACHÉ: Optimizar consulta de citas existentes
        $citasCacheKey = "citas_existentes:{$localId}:{$fechaStr}";
        $citas = Cache::remember($citasCacheKey, 180, function() use ($localId, $fechaStr) {
            return Appointment::where('premise_id', $localId)
                ->where('appointment_date', $fechaStr)
                ->get();
        });

        foreach ($citas as $cita) {
            // Filtrar los horarios que ya están ocupados por citas
            $horaOcupada = $cita->appointment_time;

            Log::info("[AgendarCita] Cita existente encontrada a las: {$horaOcupada}");

            $horariosDisponibles = array_filter($horariosDisponibles, function ($hora) use ($horaOcupada) {
                return $hora !== $horaOcupada;
            });
        }

        $disponible = ! empty($horariosDisponibles);
        // Si no quedan horarios disponibles, la fecha no está disponible
        return $disponible;
    }

    /**
     * Valida si una fecha está dentro del rango permitido según la configuración de intervalos
     */
    private function validarIntervaloReserva(Carbon $fecha): bool
    {
        $fechaHoy = Carbon::today();
        
        // Calcular fecha mínima permitida
        $fechaMinima = $fechaHoy->copy();
        if ($this->minReservationTime && $this->minTimeUnit) {
            switch ($this->minTimeUnit) {
                case 'days':
                    $fechaMinima->addDays($this->minReservationTime);
                    break;
                case 'hours':
                    $fechaMinima->addHours($this->minReservationTime);
                    break;
                case 'minutes':
                    $fechaMinima->addMinutes($this->minReservationTime);
                    break;
            }
        }

        // Calcular fecha máxima permitida
        $fechaMaxima = $fechaHoy->copy();
        if ($this->maxReservationTime && $this->maxTimeUnit) {
            switch ($this->maxTimeUnit) {
                case 'days':
                    $fechaMaxima->addDays($this->maxReservationTime);
                    break;
                case 'hours':
                    $fechaMaxima->addHours($this->maxReservationTime);
                    break;
                case 'minutes':
                    $fechaMaxima->addMinutes($this->maxReservationTime);
                    break;
            }
        }

        // Verificar si la fecha está dentro del rango permitido
        $dentroDelRango = $fecha->gte($fechaMinima->startOfDay()) && $fecha->lte($fechaMaxima->endOfDay());

        if (!$dentroDelRango) {
            Log::info("[AgendarCita] Fecha {$fecha->format('Y-m-d')} fuera del rango permitido:", [
                'fecha_minima' => $fechaMinima->format('Y-m-d'),
                'fecha_maxima' => $fechaMaxima->format('Y-m-d'),
                'min_time' => $this->minReservationTime,
                'min_unit' => $this->minTimeUnit,
                'max_time' => $this->maxReservationTime,
                'max_unit' => $this->maxTimeUnit,
            ]);
        }

        return $dentroDelRango;
    }



    public function cargarHorariosDisponibles(): void
    {
        if (empty($this->fechaSeleccionada) || empty($this->localSeleccionado)) {
            $this->horariosDisponibles = [];
            $this->slotsCompletos = []; // ✅ FIX: Limpiar estado
            $this->debugInfo = [
                'status' => 'Sin datos',
                'details' => 'Selecciona fecha y local para cargar horarios',
                'total_slots' => 0,
                'validation_method' => 'N/A'
            ];
            return;
        }

        try {
            $fecha = Carbon::createFromFormat('d/m/Y', $this->fechaSeleccionada);
            $fechaStr = $fecha->format('Y-m-d');
            $codigoLocal = $this->localSeleccionado;

            // ✅ FIX: Mejorar clave de cache incluyendo timestamp para validación
            $timestampValidacion = floor(time() / 60); // Revalidar cada minuto
            $cacheKey = "horarios_disponibles:{$codigoLocal}:{$fechaStr}:" . 
                       ($this->usarHorariosC4C ? 'c4c' : 'local') . ":{$timestampValidacion}";
            $cacheTtl = 180; // 3 minutos de caché

            // ✅ FIX: Intentar obtener estructura completa desde caché
            $datosCache = Cache::get($cacheKey);
            if ($datosCache !== null && is_array($datosCache)) {
                Log::info('📦 [Cache] Datos completos obtenidos desde caché', [
                    'cache_key' => $cacheKey,
                    'total_horarios' => count($datosCache['horarios'] ?? []),
                    'tiene_slots_completos' => !empty($datosCache['slots_completos'])
                ]);
                
                // ✅ FIX: Restaurar estado completo del componente
                $this->horariosDisponibles = $datosCache['horarios'] ?? [];
                $this->slotsCompletos = $datosCache['slots_completos'] ?? [];
                $this->slotsC4C = $datosCache['slots_c4c'] ?? [];
                
                $esDatosDelCache = true;
            } else {
                Log::info('🔄 [Cache] Generando horarios (no en caché)', [
                    'cache_key' => $cacheKey
                ]);

                // ✅ FIX: Limpiar estado previo antes de cargar nuevos datos
                $this->limpiarEstadoHorarios();

                // ✅ LÓGICA EXISTENTE SIN MODIFICAR
                if ($this->usarHorariosC4C && $this->estadoConexionC4C === 'connected') {
                    $this->cargarHorariosDesdeC4C($fechaStr, $codigoLocal);
                } else {
                    $this->cargarHorariosLocales($fechaStr, $codigoLocal);
                }

                // ✅ FIX: Guardar estructura completa en caché
                $datosParaCache = [
                    'horarios' => $this->horariosDisponibles,
                    'slots_completos' => $this->slotsCompletos,
                    'slots_c4c' => $this->slotsC4C,
                    'timestamp' => time()
                ];
                
                Cache::put($cacheKey, $datosParaCache, $cacheTtl);
                Log::info('💾 [Cache] Estructura completa guardada en caché', [
                    'cache_key' => $cacheKey,
                    'total_horarios' => count($this->horariosDisponibles),
                    'slots_completos_count' => count($this->slotsCompletos),
                    'ttl_seconds' => $cacheTtl
                ]);
                
                $esDatosDelCache = false;
            }

            // ✅ FIX: Aplicar validación de capacidad SIEMPRE (tanto para cache como datos frescos)
            if (!empty($this->horariosDisponibles)) {
                Log::info('🔄 [Progressive] Aplicando validación de capacidad', [
                    'total_horarios_originales' => count($this->horariosDisponibles),
                    'fecha' => $this->fechaSeleccionada,
                    'local' => $this->localSeleccionado,
                    'origen' => $esDatosDelCache ? 'cache' : 'fresco'
                ]);

                // ✅ FIX: Asegurar que convertirHorariosParaVista() siempre tenga datos necesarios
                $this->convertirHorariosParaVista();

                // Actualizar debug info
                $this->debugInfo = [
                    'status' => 'Validación aplicada',
                    'details' => 'Horarios validados con lógica citas_existentes < zTope',
                    'total_slots' => count($this->horariosDisponibles),
                    'validation_method' => 'Capacidad + zTope',
                    'origen_datos' => $esDatosDelCache ? 'cache' : 'fresco'
                ];

                // ✅ FIX: Siempre disparar evento (tanto para cache como datos frescos)
                $this->dispatch('horarios-cargados-activar-progressive');
            }
        } catch (\Exception $e) {
            Log::error('Error cargando horarios: ' . $e->getMessage());
            $this->limpiarEstadoHorarios();
        }
    }

    /**
     * Cargar horarios desde configuración local (cuando C4C no está disponible)
     */
    protected function cargarHorariosLocales(string $fechaStr, string $codigoLocal): void
    {
        try {
            Log::info('🏠 [AgendarCita] Cargando horarios locales', [
                'fecha' => $fechaStr,
                'local' => $codigoLocal
            ]);

            // Obtener horarios base del local
            $horariosBase = $this->obtenerHorariosBase();

            // Aplicar filtros de bloqueos y citas existentes
            $localId = $this->locales[$codigoLocal]['id'] ?? null;
            if (!$localId) {
                $this->horariosDisponibles = $horariosBase;
                return;
            }

            // ✅ CACHÉ: Optimizar consulta de bloqueos locales
            $bloqueosCacheKey = "bloqueos_locales:{$localId}:{$fechaStr}";
            $bloqueos = Cache::remember($bloqueosCacheKey, 300, function() use ($localId, $fechaStr) {
                return \App\Models\Bloqueo::where('premises', $localId)
                    ->whereDate('start_date', '<=', $fechaStr)
                    ->whereDate('end_date', '>=', $fechaStr)
                    ->get();
            });

            // ✅ CACHÉ: Optimizar consulta de citas existentes locales
            $citasCacheKey = "citas_locales:{$localId}:{$fechaStr}";
            $citasExistentes = Cache::remember($citasCacheKey, 180, function() use ($localId, $fechaStr) {
                return \App\Models\Appointment::where('premise_id', $localId)
                    ->where('appointment_date', $fechaStr)
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->pluck('appointment_time')
                    ->toArray();
            });

            // Filtrar horarios bloqueados
            $horariosDisponibles = collect($horariosBase)->filter(function ($hora) use ($bloqueos, $fechaStr) {
                $horaCompleta = Carbon::parse("{$fechaStr} {$hora}");

                foreach ($bloqueos as $bloqueo) {
                    $inicio = Carbon::createFromFormat('Y-m-d H:i:s', $bloqueo->start_date)->setTimeFromTimeString($bloqueo->start_time);
                    $fin = Carbon::createFromFormat('Y-m-d H:i:s', $bloqueo->end_date)->setTimeFromTimeString($bloqueo->end_time);

                    if ($horaCompleta->gte($inicio) && $horaCompleta->lt($fin)) {
                        return false; // Hora bloqueada
                    }
                }
                return true;
            });

            // Filtrar horarios ya ocupados por citas
            $horariosDisponibles = $horariosDisponibles->filter(function ($hora) use ($citasExistentes) {
                return !in_array($hora, $citasExistentes);
            });

            $this->horariosDisponibles = $horariosDisponibles->values()->toArray();

            Log::info('✅ [AgendarCita] Horarios locales cargados', [
                'total_base' => count($horariosBase),
                'total_disponibles' => count($this->horariosDisponibles),
                'bloqueos_aplicados' => $bloqueos->count(),
                'citas_existentes' => count($citasExistentes)
            ]);

        } catch (\Exception $e) {
            Log::error('❌ [AgendarCita] Error cargando horarios locales: ' . $e->getMessage());
            $this->horariosDisponibles = $this->obtenerHorariosBase();
        }
    }

    /**
     * Validación progresiva de capacidad para horarios (llamado desde Alpine.js)
     */
    public function validarCapacidadProgresiva()
    {
        // ✅ FIX: Agregar logging detallado para debug
        Log::info('🔍 [Progressive] Validando capacidad progresiva', [
            'fechaSeleccionada' => $this->fechaSeleccionada,
            'localSeleccionado' => $this->localSeleccionado,
            'fechaSeleccionada_empty' => empty($this->fechaSeleccionada),
            'localSeleccionado_empty' => empty($this->localSeleccionado)
        ]);

        if (empty($this->fechaSeleccionada) || empty($this->localSeleccionado)) {
            Log::warning('❌ [Progressive] Faltan datos requeridos', [
                'fechaSeleccionada' => $this->fechaSeleccionada ?? 'null',
                'localSeleccionado' => $this->localSeleccionado ?? 'null'
            ]);

            return $this->dispatch('progressive-validation-completed', [
                'success' => false,
                'error' => 'Faltan fecha o local'
            ]);
        }

        try {
            $fecha = Carbon::createFromFormat('d/m/Y', $this->fechaSeleccionada);
            $fechaStr = $fecha->format('Y-m-d');
            $codigoLocal = $this->localSeleccionado;

            Log::info('🔍 [Progressive] Iniciando validación de capacidad', [
                'centro' => $codigoLocal,
                'fecha' => $fechaStr
            ]);

            // Usar el AvailabilityService existente para validar capacidad
            $availabilityService = app(AvailabilityService::class);
            $result = $availabilityService->getAvailableSlots($codigoLocal, $fechaStr);

            if ($result['success']) {
                Log::info('✅ [Progressive] Validación completada', [
                    'total_slots' => $result['total_slots'],
                    'available_slots' => $result['available_slots']
                ]);

                // Actualizar debug info con resultados de validación
                $slotsValidados = 0;
                $slotsDisponibles = 0;
                $detalles = [];

                foreach ($result['slots'] as $slot) {
                    if (isset($slot['capacity_validation']['validated']) && $slot['capacity_validation']['validated']) {
                        $slotsValidados++;
                        $status = $slot['is_available'] ? '✅' : '❌';
                        $maxCap = $slot['capacity_validation']['max_capacity'] ?? 'N/A';
                        $existing = $slot['capacity_validation']['existing_appointments'] ?? 'N/A';
                        $remaining = $slot['capacity_validation']['remaining_capacity'] ?? 'N/A';
                        $hcpCount = $slot['capacity_validation']['hcp_count'] ?? 'N/A';
                        $noHcpCount = $slot['capacity_validation']['no_hcp_count'] ?? 'N/A';
                        $platesArr = $slot['capacity_validation']['plates'] ?? [];
                        $platesStr = is_array($platesArr) && count($platesArr) ? implode(', ', $platesArr) : '';

                        $detalles[] = "{$status} {$slot['start_time_formatted']} | zTope: {$maxCap} | Citas: {$existing} | Libre: {$remaining} | HCP: {$hcpCount} (-5h) | No-HCP: {$noHcpCount}" . ($platesStr ? " | Placas: {$platesStr}" : '');

                        if ($slot['is_available']) {
                            $slotsDisponibles++;
                        }
                    }
                }

                // Extraer métricas HCP si están presentes
                $hcpCount = $result['hcp_stats']['hcp'] ?? null;
                $noHcpCount = $result['hcp_stats']['no_hcp'] ?? null;

                $this->debugInfo = [
                    'status' => 'Validación completada ✅',
                    'details' => implode("\n", $detalles), // Mostrar TODOS los horarios
                    'total_slots' => $result['total_slots'],
                    'validation_method' => 'C4C BATCH + zTope',
                    'slots_validados' => $slotsValidados,
                    'slots_disponibles' => $slotsDisponibles,
                    'hcp' => $hcpCount,
                    'no_hcp' => $noHcpCount,
                    'total_citas' => $result['total_appointments'] ?? 'N/A',
                ];

                // Enviar resultado a Alpine.js
                $this->dispatch('progressive-validation-completed', [
                    'success' => true,
                    'slots' => $result['slots']
                ]);
            } else {
                throw new \Exception($result['error']);
            }

        } catch (\Exception $e) {
            Log::error('❌ [Progressive] Error en validación', [
                'error' => $e->getMessage()
            ]);

            $this->dispatch('progressive-validation-completed', [
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }



    private function obtenerHorariosBase(): array
    {
        $horarios = [];

        // Si no hay local seleccionado, usar horarios predeterminados
        if (empty($this->localSeleccionado) || empty($this->locales[$this->localSeleccionado])) {
            Log::warning('[AgendarCita] No hay local seleccionado para obtener horarios base, usando predeterminados');

            // Horarios predeterminados de 8:00 AM a 5:00 PM, cada 30 minutos
            for ($hora = 8; $hora <= 17; $hora++) {
                $horarios[] = sprintf('%02d:00:00', $hora);

                // No agregar los 30 minutos para las 5:00 PM
                if ($hora < 17) {
                    $horarios[] = sprintf('%02d:30:00', $hora);
                }
            }

            // Convertir todos los horarios a formato HH:mm
            $horarios = array_map(function ($h) {
                return date('H:i', strtotime($h));
            }, $horarios);

            return $horarios;
        }

        // Obtener los horarios del local seleccionado
        $local = $this->locales[$this->localSeleccionado];
        $horaApertura = $local['opening_time'];
        $horaCierre = $local['closing_time'];

        Log::info("[AgendarCita] Horarios del local seleccionado: {$horaApertura} - {$horaCierre}");

        // Convertir a objetos Carbon para facilitar la manipulación
        try {
            $apertura = Carbon::createFromFormat('H:i:s', $horaApertura);
            $cierre = Carbon::createFromFormat('H:i:s', $horaCierre);

            // Asegurarse de que la hora de cierre sea posterior a la de apertura
            if ($apertura->gt($cierre)) {
                Log::warning('[AgendarCita] Hora de apertura posterior a la de cierre, usando horarios predeterminados');

                return $this->obtenerHorariosBaseDefault();
            }

            // Obtener la hora de apertura y cierre en formato de hora
            $horaAperturaInt = (int) $apertura->format('H');
            $horaCierreInt = (int) $cierre->format('H');

            // Generar horarios cada 15 minutos desde la apertura hasta el cierre
            for ($hora = $horaAperturaInt; $hora <= $horaCierreInt; $hora++) {
                // Agregar los 4 intervalos de 15 minutos por hora
                $horarios[] = sprintf('%02d:00:00', $hora);

                // Agregar los otros intervalos, excepto para la última hora
                if ($hora < $horaCierreInt) {
                    $horarios[] = sprintf('%02d:15', $hora);
                    $horarios[] = sprintf('%02d:30', $hora);
                    $horarios[] = sprintf('%02d:45', $hora);
                }
            }

            // Convertir todos los horarios a formato HH:mm
            $horarios = array_map(function ($h) {
                return date('H:i', strtotime($h));
            }, $horarios);


            Log::info('[AgendarCita] Horarios base generados: ' . count($horarios));

            return $horarios;
        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al procesar horarios del local: ' . $e->getMessage());

            return $this->obtenerHorariosBaseDefault();
        }
    }

    /**
     * Obtiene los horarios base predeterminados (8:00 AM a 5:00 PM, cada 30 minutos)
     */
    private function obtenerHorariosBaseDefault(): array
    {
        $horarios = [];

        // Horarios predeterminados de 8:00 AM a 5:00 PM, cada 30 minutos
        for ($hora = 8; $hora <= 17; $hora++) {
            $horarios[] = sprintf('%02d:00', $hora);

            // No agregar los 30 minutos para las 5:00 PM
            if ($hora < 17) {
                $horarios[] = sprintf('%02d:30', $hora);
            }
        }

        // Convertir todos los horarios a formato HH:mm
        $horarios = array_map(function ($h) {
            return date('H:i', strtotime($h));
        }, $horarios);


        return $horarios;
    }

    /**
     * Cambia el mes del calendario
     */
    public function cambiarMes(int $cambio): void
    {
        // Crear una fecha con el mes y año actual
        $fecha = Carbon::createFromDate($this->anoActual, $this->mesActual, 1);

        // Sumar o restar meses según el cambio
        $nuevaFecha = $cambio > 0 ? $fecha->addMonths(abs($cambio)) : $fecha->subMonths(abs($cambio));

        // Actualizar el mes y año actual
        $this->mesActual = $nuevaFecha->month;
        $this->anoActual = $nuevaFecha->year;

        // Regenerar el calendario
        $this->generarCalendario();
    }

    /**
     * Cambia el año del calendario
     */
    public function cambiarAno(int $cambio): void
    {
        // Sumar o restar años según el cambio
        $this->anoActual += $cambio;

        // Regenerar el calendario
        $this->generarCalendario();
    }

    /**
     * Selecciona una fecha y carga los horarios disponibles
     */
    public function seleccionarFecha(string $fecha): void
    {
        // Verificar si la fecha es válida
        try {
            $fechaCarbon = Carbon::createFromFormat('d/m/Y', $fecha)->startOfDay();

            // Verificar si la fecha es pasada o es hoy
            if ($fechaCarbon->lte(Carbon::today()) || $fechaCarbon->isSameDay(Carbon::today())) {
                Log::warning("[AgendarCita] Intento de seleccionar fecha pasada o actual: {$fecha}");

                // No permitir seleccionar fechas pasadas o el día de hoy
                return;
            }

            // Verificar si la fecha está dentro del rango permitido por los intervalos
            if (!$this->validarIntervaloReserva($fechaCarbon)) {
                Log::warning("[AgendarCita] Intento de seleccionar fecha fuera del rango permitido: {$fecha}");
                return;
            }

            // Si la fecha ya está seleccionada, deseleccionarla
            if ($this->fechaSeleccionada === $fecha) {
                Log::info("[AgendarCita] Deseleccionando fecha: {$fecha}");
                $this->fechaSeleccionada = '';
                $this->limpiarEstadoHorarios(); // ✅ FIX: Usar método centralizado

                return;
            }

            // ✅ FIX: Limpiar estado previo ANTES de seleccionar nueva fecha
            $this->limpiarEstadoHorarios();

            // Actualizar la fecha seleccionada
            $this->fechaSeleccionada = $fecha;
            Log::info("[AgendarCita] Fecha seleccionada: {$this->fechaSeleccionada}");

            // Regenerar el calendario para actualizar la visualización
            $this->generarCalendario();

            // Cargar los horarios disponibles para esta fecha
            $this->cargarHorariosDisponibles();

        } catch (\Exception $e) {
            Log::error('[AgendarCita] Error al seleccionar fecha: ' . $e->getMessage());
            $this->limpiarEstadoHorarios(); // ✅ FIX: Limpiar estado en caso de error
        }
    }

    /**
     * Selecciona una hora
     */
    public function seleccionarHora(string $hora): void
    {
        Log::info("[AgendarCita] Intentando seleccionar hora: {$hora}");

        // ✅ SMART: Verificar disponibilidad en nueva estructura de datos
        $horaDisponible = false;
        $horaSeleccionable = false;
        
        foreach ($this->horariosDisponibles as $horario) {
            if (is_array($horario)) {
                // Nueva estructura con is_available
                if ($horario['time'] === $hora) {
                    $horaDisponible = true;
                    $horaSeleccionable = $horario['is_available'] ?? false;
                    break;
                }
            } elseif ($horario === $hora) {
                // Estructura legacy (string)
                $horaDisponible = true;
                $horaSeleccionable = true;
                break;
            }
        }

        if ($horaDisponible && $horaSeleccionable) {
            // Si ya está seleccionada, deseleccionarla
            if ($this->horaSeleccionada === $hora) {
                Log::info("[AgendarCita] Deseleccionando hora: {$hora}");
                $this->horaSeleccionada = '';
            } else {
                // Actualizar la hora seleccionada
                $this->horaSeleccionada = $hora;
                Log::info("[AgendarCita] Hora seleccionada: {$this->horaSeleccionada}");
            }
        } else {
            Log::warning("[AgendarCita] Intento de seleccionar hora no disponible: {$hora}", [
                'hora_encontrada' => $horaDisponible,
                'hora_seleccionable' => $horaSeleccionable
            ]);
            // Notificar al usuario
            $this->notify('error', 'La hora seleccionada no está disponible');
        }
    }

    /**
     * Muestra una notificación al usuario
     */
    protected function notify(string $type, string $message): void
    {
        $this->dispatch('notify', [
            'type' => $type,
            'message' => $message,
        ]);
    }

    /**
     * Actualiza los horarios cuando cambia el local seleccionado
     */
    public function updatedLocalSeleccionado($value): void
    {
        Log::info("[AgendarCita] Local seleccionado cambiado a: {$value}");

        // Limpiar la hora seleccionada
        $this->horaSeleccionada = '';

        // Limpiar los horarios disponibles para forzar su recarga
        $this->horariosDisponibles = [];

        // ✅ NUEVO: Recargar configuración de intervalos específica del local
        $this->cargarConfiguracionIntervalos();
        Log::info("[AgendarCita] Configuración de intervalos recargada para local: {$value}");

        // Regenerar el calendario para actualizar la disponibilidad según el local seleccionado
        $this->generarCalendario();

        // Si hay una fecha seleccionada, cargar los horarios disponibles para el nuevo local
        if (! empty($this->fechaSeleccionada)) {
            Log::info("[AgendarCita] Recargando horarios para fecha: {$this->fechaSeleccionada} y local: {$value}");
            $this->cargarHorariosDisponibles();
        }

        // Recargar las campañas
        Log::info("[AgendarCita] === RECARGANDO CAMPAÑAS POR CAMBIO DE LOCAL ===");
        $this->cargarCampanas();
        Log::info("[AgendarCita] Campañas recargadas después de cambiar el local a: {$value}. Total: " . count($this->campanasDisponibles));

        // Recargar las modalidades disponibles para el nuevo local
        $this->cargarModalidadesDisponibles();

        // Si la modalidad actual ya no está disponible, cambiar a Regular
        if (! array_key_exists($this->modalidadServicio, $this->modalidadesDisponibles)) {
            $this->modalidadServicio = 'Regular';
            Log::info('[AgendarCita] Modalidad cambiada a Regular porque la anterior no está disponible en el nuevo local');
        }

        // Forzar la actualización de la vista
        $this->dispatch('horarios-actualizados');
    }

    /**
     * Listener para cuando cambia el tipo de mantenimiento
     */
    public function updatedTipoMantenimiento(): void
    {
        // Recargar las modalidades disponibles para el nuevo tipo de mantenimiento
        $this->cargarModalidadesDisponibles();

        // Si la modalidad actual ya no está disponible, cambiar a Regular
        if (!array_key_exists($this->modalidadServicio, $this->modalidadesDisponibles)) {
            $this->modalidadServicio = 'Regular';
        }


        $this->cargarServiciosAdicionalesDisponibles();

        // NUEVO: Obtener paquete ID automáticamente
        $this->obtenerPaqueteId();
    }

    /**
     * Obtiene el nombre del mes actual
     */
    public function getNombreMesActualProperty(): string
    {
        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return $meses[$this->mesActual] ?? '';
    }

    /**
     * Genera la URL correcta para una imagen de campaña
     */
    private function getImageUrl($imagen): string
    {
        $rutaOriginal = $imagen->route;

        // Verificar si la imagen está en la carpeta private (imágenes antiguas)
        if (str_contains($rutaOriginal, 'private/public/')) {
            // Para imágenes en private, crear una ruta especial
            $nombreArchivo = basename($rutaOriginal);
            $url = route('imagen.campana', ['idOrFilename' => $nombreArchivo]);

            return $url;
        }

        // Para imágenes nuevas en public
        $rutaLimpia = str_replace('public/', '', $rutaOriginal);
        $url = asset('storage/' . $rutaLimpia);

        return $url;
    }

    /**
     * Obtener paquete ID basado en el tipo de mantenimiento
     * Con sistema de prioridades para clientes normales
     */
    public function obtenerPaqueteId(): void
    {
        try {
            $vehicle = null;
            if (!empty($this->vehiculo['placa'])) {
                $vehicle = \App\Models\Vehicle::where('license_plate', $this->vehiculo['placa'])->first();
            }

            if (!$vehicle) {
                $this->paqueteId = null;
                return;
            }

            $productService = app(ProductService::class);
            $user = Auth::user();
            $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

            // ✅ PARA CLIENTES WILDCARD: Usar lógica original (solo mantenimiento)
            if ($isWildcardClient) {
                if (!$this->tipoMantenimiento) {
                    $this->paqueteId = null;
                    return;
                }
                
                $this->paqueteId = $productService->obtenerPaquetePorTipo($this->tipoMantenimiento, $vehicle);
                
                Log::info('📦 Paquete ID obtenido (cliente wildcard)', [
                    'tipo_mantenimiento' => $this->tipoMantenimiento,
                    'paquete_id' => $this->paqueteId
                ]);
                return;
            }

            // ✅ PARA CLIENTES NORMALES: Usar sistema de prioridades
            
            // Preparar datos de servicios adicionales
            $serviciosAdicionales = [];
            if (!empty($this->serviciosAdicionales)) {
                foreach ($this->serviciosAdicionales as $servicioId) {
                    // Extraer ID numérico del formato "servicio_X"
                    $id = str_replace('servicio_', '', $servicioId);
                    
                    // Buscar el servicio por ID para obtener su código
                    $servicio = AdditionalService::where('id', $id)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($servicio) {
                        $serviciosAdicionales[] = [
                            'nombre' => $servicio->name, 
                            'code' => $servicio->code
                        ];
                    }
                }
            }
            
            // Preparar datos de campañas
            $campanasSeleccionadas = [];
            if (!empty($this->campanaSeleccionada)) {
                // Buscar la campaña en la base de datos para obtener su código real
                $campana = Campana::where('id', $this->campanaSeleccionada)
                    ->where('status', 'Activo')
                    ->first();
                
                if ($campana) {
                    $campanasSeleccionadas[] = [
                        'nombre' => $campana->title, 
                        'code' => $campana->code
                    ];
                }
            }
            
            $this->paqueteId = $productService->calculatePackageIdWithPriority(
                $vehicle,
                $this->tipoMantenimiento,
                $serviciosAdicionales,
                $campanasSeleccionadas
            );

            Log::info('📦 Paquete ID obtenido (cliente normal con prioridades)', [
                'tipo_mantenimiento' => $this->tipoMantenimiento,
                'servicios_adicionales' => count($serviciosAdicionales),
                'campanas_seleccionadas' => count($campanasSeleccionadas),
                'paquete_id' => $this->paqueteId
            ]);
        } catch (\Exception $e) {
            Log::error('💥 Error obteniendo paquete ID', [
                'error' => $e->getMessage()
            ]);
            $this->paqueteId = null;
        }
    }

    /**
     * Validar vehículo en C4C
     */
    public function validarVehiculo($vehiculoId): void
    {
        try {
            if (!$vehiculoId) {
                $this->vehiculoValidado = false;
                return;
            }

            // Obtener placa del vehículo actual
            $placa = $this->vehiculo['placa'] ?? null;
            if (!$placa) {
                $this->vehiculoValidado = false;
                $this->errorValidacionVehiculo = 'No se encontró placa del vehículo';
                return;
            }

            // Validar en C4C
            $vehicleService = app(VehicleService::class);
            $resultado = $vehicleService->obtenerVehiculoPorPlaca($placa);

            if ($resultado['success'] && $resultado['found']) {
                $this->vehiculoValidado = true;
                $this->datosVehiculo = (array) $resultado['data'];
                $this->errorValidacionVehiculo = '';

                Log::info('✅ Vehículo validado en C4C', [
                    'placa' => $placa,
                    'vehiculo_id' => $this->datosVehiculo['VehicleID'] ?? 'N/A'
                ]);
            } else {
                $this->vehiculoValidado = false;
                $this->errorValidacionVehiculo = 'Vehículo no encontrado en C4C';

                Log::warning('⚠️ Vehículo no encontrado en C4C', [
                    'placa' => $placa
                ]);
            }
        } catch (\Exception $e) {
            $this->vehiculoValidado = false;
            $this->errorValidacionVehiculo = 'Error validando vehículo: ' . $e->getMessage();

            Log::error('💥 Error validando vehículo', [
                'error' => $e->getMessage(),
                'placa' => $placa ?? 'N/A'
            ]);
        }
    }

    /**
     * Crear cita con integración completa C4C
     */
    public function crearCita(): void
    {
        try {
            Log::info('🎯 Iniciando creación de cita completa', [
                'tipo_mantenimiento' => $this->tipoMantenimiento,
                'paquete_id' => $this->paqueteId,
                'vehiculo_validado' => $this->vehiculoValidado,
                'local_seleccionado' => $this->localSeleccionado
            ]);

            // Validaciones básicas existentes
            $this->validarFormulario();

            // Obtener paquete ID si no se ha obtenido y hay elementos para procesar
            if (!$this->paqueteId && ($this->tipoMantenimiento || !empty($this->serviciosAdicionales) || !empty($this->campanaSeleccionada))) {
                $this->obtenerPaqueteId();
            }

            // Validación adicional: verificar que se obtuvo paquete ID
            if (!$this->paqueteId) {
                Log::warning('⚠️ No se pudo obtener paquete ID', [
                    'tipo_mantenimiento' => $this->tipoMantenimiento
                ]);

                session()->flash('error', 'Error: No se pudo determinar el paquete de mantenimiento. Contacte al administrador.');
                return;
            }

            // Crear la cita con todos los datos
            $appointment = new Appointment();

            // Datos básicos (mantener lógica existente)
            $user = Auth::user();
            $appointment->customer_ruc = $user->document_number;
            $appointment->customer_name = $this->nombreCliente;
            $appointment->customer_last_name = $this->apellidoCliente;
            $appointment->customer_email = $this->emailCliente;
            $appointment->customer_phone = $this->celularCliente;
            $appointment->vehicle_id = $this->vehiculo['id'];
            $appointment->premise_id = $this->locales[$this->localSeleccionado]['id'];
            $appointment->appointment_date = Carbon::createFromFormat('d/m/Y', $this->fechaSeleccionada);
            $appointment->appointment_time = $this->horaSeleccionada;
            $appointment->maintenance_type = $this->tipoMantenimiento;
            $appointment->status = 'pending';

            // NUEVOS CAMPOS PARA INTEGRACIÓN COMPLETA
            // ✅ DETECTAR CLIENTE COMODÍN ANTES DE ASIGNAR PACKAGE_ID
            $user = Auth::user();
            $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

            // Solo asignar package_id si NO es cliente comodín
            $appointment->package_id = $isWildcardClient ? null : $this->paqueteId;
            $appointment->vehicle_plate = $this->vehiculo['placa'];

            // Generar número de cita
            $appointment->appointment_number = $this->generarNumeroCita();

            // Servicios adicionales (mantener lógica existente)
            if (!empty($this->serviciosAdicionalesSeleccionados)) {
                $appointment->additional_services = json_encode($this->serviciosAdicionalesSeleccionados);
            }

            // Comentarios
            if (!empty($this->comentarios)) {
                $appointment->comments = $this->comentarios;
            }

            $appointment->save();

            Log::info('✅ Cita creada exitosamente', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'package_id' => $appointment->package_id,
                'vehicle_plate' => $appointment->vehicle_plate,
                'customer_id' => $appointment->customer_id,
                'status' => $appointment->status,
                'appointment_date' => $appointment->appointment_date->format('Y-m-d'),
                'appointment_time' => $appointment->appointment_time
            ]);

            // INTEGRACIÓN COMPLETA - PROCESOS ASÍNCRONOS
            try {
                // 1. Sincronizar con C4C (incluye paquete ID)
                SyncAppointmentToC4CJob::dispatch($appointment);
                Log::info('📤 Job de sincronización despachado', [
                    'appointment_id' => $appointment->id
                ]);

                // 1.1. Procesar actualizaciones post-creación
                ProcessAppointmentAfterCreationJob::dispatch($appointment->id)
                    ->delay(now()->addMinutes(1));
                Log::info('📤 Job de procesamiento post-creación despachado', [
                    'appointment_id' => $appointment->id,
                    'delay' => '1 minuto'
                ]);

                // 2. Crear oferta como fallback (solo si tiene paquete Y NO es cliente comodín)
                // ✅ DETECTAR CLIENTE COMODÍN ANTES DE DISPARAR CreateOfferJob
                $user = Auth::user();
                $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

                // 🔍 DEBUG: Log detallado de la lógica CreateOfferJob
                Log::info('🔍 [AgendarCita] Lógica CreateOfferJob', [
                    'appointment_id' => $appointment->id,
                    'paquete_id' => $this->paqueteId,
                    'user_c4c_id' => $user ? $user->c4c_internal_id : 'NO_USER',
                    'is_wildcard' => $isWildcardClient,
                    'condition_paqueteId' => $this->paqueteId ? 'TRUE' : 'FALSE',
                    'condition_not_wildcard' => !$isWildcardClient ? 'TRUE' : 'FALSE',
                    'condition_normal' => ($this->paqueteId && !$isWildcardClient) ? 'TRUE' : 'FALSE',
                    'condition_wildcard' => $isWildcardClient ? 'TRUE' : 'FALSE'
                ]);

                // Para clientes normales: NO disparar CreateOfferJob aquí.
                // La oferta se generará después de descargar productos del paquete correcto (DownloadProductsJob).
                if ($isWildcardClient) {
                    // Para clientes wildcard, disparar CreateOfferJob inmediatamente (sin delay)
                    CreateOfferJob::dispatch($appointment);
                    Log::info('📤 Job de creación de oferta wildcard despachado', [
                        'appointment_id' => $appointment->id,
                        'is_wildcard' => true,
                        'delay' => 'inmediato'
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('💥 Error despachando jobs', [
                    'appointment_id' => $appointment->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
                // NO re-throw para no romper el flujo de la UI
            }

            // **EMAIL SE ENVIARÁ DESPUÉS DEL ÉXITO EN C4C** 📧
            // El email de confirmación se enviará desde EnviarCitaC4CJob cuando la sincronización sea exitosa
            Log::info('📧 [AgendarCita] Email de confirmación se enviará después del éxito en C4C (flujo completo)', [
                'appointment_id' => $appointment->id,
                'customer_email' => $this->emailCliente
            ]);

            // Limpiar formulario
            $this->limpiarFormulario();

            // Mostrar éxito
            session()->flash('success', "¡Cita agendada exitosamente! Número: {$appointment->appointment_number}");

            // Marcar como completada
            $this->citaAgendada = true;
            $this->appointmentNumber = $appointment->appointment_number;
            
            // Limpiar caché de citas pendientes para forzar actualización en página de vehículos
            $this->limpiarCacheCitasPendientes();
            
            // Establecer flag en sesión para que la página de vehículos se actualice
            session()->put('cita_agendada_recientemente', [
                'vehiculo_placa' => $this->vehiculo['placa'],
                'appointment_number' => $appointment->appointment_number,
                'timestamp' => now()->timestamp
            ]);
            
            // Emitir evento para actualizar página de vehículos
            $this->dispatch('citaAgendadaExitosamente', [
                'vehiculo_placa' => $this->vehiculo['placa'],
                'appointment_number' => $appointment->appointment_number
            ]);
            
            // También emitir evento JavaScript para localStorage
            $this->dispatchBrowserEvent('citaAgendadaExitosamente', [
                'vehiculo_placa' => $this->vehiculo['placa'],
                'appointment_number' => $appointment->appointment_number,
                'timestamp' => now()->timestamp
            ]);
        } catch (\Exception $e) {
            Log::error('💥 Error en creación de cita', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->flash('error', 'Error al agendar cita. Intente nuevamente.');
        }
    }

    /**
     * Limpiar caché de citas pendientes para forzar actualización
     */
    protected function limpiarCacheCitasPendientes(): void
    {
        try {
            $user = Auth::user();
            if ($user && $user->c4c_internal_id) {
                $cacheKey = "citas_pendientes_{$user->c4c_internal_id}";
                \Illuminate\Support\Facades\Cache::forget($cacheKey);
                Log::info("[AgendarCita] Caché de citas pendientes limpiado para usuario: {$user->c4c_internal_id}");
            }
        } catch (\Exception $e) {
            Log::error("[AgendarCita] Error al limpiar caché de citas: " . $e->getMessage());
        }
    }

    /**
     * Validar formulario antes de crear cita
     */
    protected function validarFormulario(): void
    {
        // Validaciones básicas existentes
        if (empty($this->fechaSeleccionada)) {
            throw new \Exception('Debe seleccionar una fecha');
        }

        if (empty($this->horaSeleccionada)) {
            throw new \Exception('Debe seleccionar una hora');
        }

        if (empty($this->localSeleccionado)) {
            throw new \Exception('Debe seleccionar un local');
        }

        if (empty($this->tipoMantenimiento)) {
            throw new \Exception('Debe seleccionar un tipo de mantenimiento');
        }

        // Validaciones adicionales para integración C4C
        if (empty($this->vehiculo['placa'])) {
            throw new \Exception('No se encontró placa del vehículo');
        }

        if (empty($this->vehiculo['id'])) {
            throw new \Exception('No se encontró ID del vehículo');
        }
    }

    /**
     * Generar número único de cita
     */
    protected function generarNumeroCita(): string
    {
        $prefix = 'CITA';
        $timestamp = now()->format('YmdHis');
        $random = str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Limpiar formulario después de crear cita
     */
    protected function limpiarFormulario(): void
    {
        $this->fechaSeleccionada = '';
        $this->horaSeleccionada = '';
        $this->tipoMantenimiento = '';
        $this->modalidadServicio = '';
        $this->serviciosAdicionalesSeleccionados = [];
        $this->comentarios = '';
        $this->paqueteId = null;
        $this->datosVehiculo = [];
        $this->vehiculoValidado = false;
        $this->errorValidacionVehiculo = '';
    }

    /**
     * Enviar email de confirmación de cita creada
     */
    protected function enviarEmailConfirmacion(Appointment $appointment): void
    {
        try {
            Log::info('📧 [CitaCreada] Enviando email de confirmación', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_email' => $this->emailCliente
            ]);

            // Preparar datos del cliente
            $datosCliente = [
                'nombres' => $this->nombreCliente,
                'apellidos' => $this->apellidoCliente,
                'email' => $this->emailCliente,
                'celular' => $this->celularCliente,
            ];

            // Preparar datos del vehículo
            $datosVehiculo = [
                'marca' => $this->vehiculo['marca'] ?? 'No especificado',
                'modelo' => $this->vehiculo['modelo'] ?? 'No especificado',
                'placa' => $this->vehiculo['placa'] ?? 'No especificado',
            ];

            // Enviar el correo de confirmación
            Mail::to($this->emailCliente)
                ->send(new CitaCreada($appointment, $datosCliente, $datosVehiculo));

            Log::info('📧 [CitaCreada] Email de confirmación enviado exitosamente', [
                'appointment_id' => $appointment->id,
                'customer_email' => $this->emailCliente,
            ]);

        } catch (\Exception $e) {
            Log::error('📧 [CitaCreada] Error enviando email de confirmación', [
                'appointment_id' => $appointment->id,
                'customer_email' => $this->emailCliente,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // No lanzar excepción para no interrumpir el proceso de creación de cita
            // Solo registrar el error
        }
    }

    /**
     * Enviar email de notificación de cita editada
     */
    protected function enviarEmailCitaEditada(Appointment $appointment, array $cambiosRealizados = []): void
    {
        try {
            Log::info('📧 [CitaEditada] Enviando email de cita editada', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'customer_email' => $appointment->customer_email,
                'cambios' => $cambiosRealizados,
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

            // Cargar relaciones antes de enviar el email
            $appointment->load('additionalServices.additionalService');
            
            // Enviar el correo de edición
            Mail::to($appointment->customer_email)
                ->send(new CitaEditada($appointment, $datosCliente, $datosVehiculo, $cambiosRealizados));

            Log::info('📧 [CitaEditada] Email de cita editada enviado exitosamente', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email,
            ]);

        } catch (\Exception $e) {
            Log::error('📧 [CitaEditada] Error enviando email de cita editada', [
                'appointment_id' => $appointment->id,
                'customer_email' => $appointment->customer_email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
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
                'marca' => $appointment->vehicle_brand ?? 'No especificado',
                'modelo' => $appointment->vehicle_model ?? 'No especificado',
                'placa' => $appointment->vehicle_license_plate ?? 'No especificado',
            ];

            // Cargar relaciones antes de enviar el email
            $appointment->load('additionalServices.additionalService');
            
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
        }
    }

    /**
     * Detectar si estamos en modo edición y cargar datos originales
     */
    protected function detectarModoEdicion(): void
    {
        Log::info('🔍 [AgendarCita::detectarModoEdicion] ========== DETECTANDO MODO EDICIÓN ==========');
        Log::info('[AgendarCita::detectarModoEdicion] Parámetros recibidos', [
            'editMode' => request()->query('editMode'),
            'all_params' => request()->query()
        ]);

        if (request()->query('editMode') === 'true') {
            Log::info('✅ [AgendarCita::detectarModoEdicion] MODO EDICIÓN ACTIVADO');

            $this->editMode = true;
            $this->originalCitaId = request()->query('originalCitaId');
            $this->originalUuid = request()->query('originalUuid');
            $this->originalCenterId = request()->query('originalCenterId');
            $this->originalDate = request()->query('originalDate');
            $this->originalTime = request()->query('originalTime');
            $this->originalServicio = request()->query('originalServicio');
            $this->originalSede = request()->query('originalSede');

            Log::info('[AgendarCita] Modo edición detectado', [
                'edit_mode' => $this->editMode,
                'original_cita_id' => $this->originalCitaId,
                'original_uuid' => $this->originalUuid,
                'original_center_id' => $this->originalCenterId,
                'original_date' => $this->originalDate,
                'original_time' => $this->originalTime,
                'original_servicio' => $this->originalServicio,
                'original_sede' => $this->originalSede
            ]);

            // Preseleccionar centro si está disponible
            if ($this->originalCenterId) {
                $this->localSeleccionado = $this->originalCenterId;
                Log::info('[AgendarCita::detectarModoEdicion] Centro preseleccionado: ' . $this->originalCenterId);
            }
        } else {
            Log::info('❌ [AgendarCita::detectarModoEdicion] Modo edición NO detectado');
        }

        Log::info('🏁 [AgendarCita::detectarModoEdicion] ========== FIN DETECCIÓN ==========');
    }

    /**
     * Reprogramar cita existente - actualiza original y crea nueva
     */
    public function reprogramarCita(): void
    {
        Log::info('🔄 [AgendarCita::reprogramarCita] ========== INICIO REPROGRAMACIÓN ==========');

        if (!$this->editMode || !$this->originalUuid) {
            Log::error('❌ [AgendarCita::reprogramarCita] No estamos en modo edición o falta UUID original', [
                'edit_mode' => $this->editMode,
                'original_uuid' => $this->originalUuid
            ]);
            return;
        }

        // Verificar si ya estamos procesando esta reprogramación para evitar loops
        static $processingUuids = [];
        if (isset($processingUuids[$this->originalUuid])) {
            Log::warning('⚠️ [AgendarCita::reprogramarCita] Ya se está procesando la reprogramación de este UUID', [
                'uuid' => $this->originalUuid
            ]);
            return;
        }
        $processingUuids[$this->originalUuid] = true;

        Log::info('[AgendarCita::reprogramarCita] Iniciando reprogramación', [
            'original_uuid' => $this->originalUuid,
            'nueva_fecha' => $this->fechaSeleccionada,
            'nueva_hora' => $this->horaSeleccionada,
            'datos_cliente' => [
                'nombre' => $this->nombreCliente,
                'apellido' => $this->apellidoCliente,
                'email' => $this->emailCliente
            ]
        ]);

        try {
            // 1. ACTUALIZAR cita original a estado "Diferida" (4)
            $appointmentService = app(\App\Services\C4C\AppointmentService::class);

            Log::info('[AgendarCita::reprogramarCita] Enviando actualización a C4C', [
                'uuid' => $this->originalUuid,
                'new_status' => 4,
                'operation' => 'change_to_diferida'
            ]);

            // Usar método simplificado que solo actualiza el estado
            $updateResult = $appointmentService->updateStatus($this->originalUuid, 4);

            if (!$updateResult['success']) {
                throw new \Exception('Error al diferir cita original: ' . ($updateResult['error'] ?? 'Error desconocido'));
            }

            Log::info('[AgendarCita::reprogramarCita] Cita original actualizada a Diferida');

            // ✅ MARCAR LA CITA ORIGINAL COMO REPROGRAMADA EN LA BD LOCAL
            $originalAppointment = Appointment::where('c4c_uuid', $this->originalUuid)->first();
            if ($originalAppointment) {
                $originalAppointment->update([
                    'status' => 'cancelled',
                    'rescheduled' => 1
                ]);
                
                Log::info('[AgendarCita::reprogramarCita] Cita original marcada como reprogramada', [
                    'appointment_id' => $originalAppointment->id,
                    'appointment_number' => $originalAppointment->appointment_number,
                    'rescheduled' => 1
                ]);
            } else {
                Log::warning('[AgendarCita::reprogramarCita] No se encontró la cita original en BD local', [
                    'uuid' => $this->originalUuid
                ]);
            }

            // 2. CREAR nueva cita con datos seleccionados - LLAMAR DIRECTAMENTE A CREACIÓN
            $this->crearNuevaCitaReprogramada();

            // Limpiar flag de procesamiento al finalizar exitosamente
            unset($processingUuids[$this->originalUuid]);

            // IMPORTANTE: Terminar ejecución aquí para evitar que continúe el flujo
            Log::info('🏁 [AgendarCita::reprogramarCita] Proceso completado - terminando ejecución');
            return;
        } catch (\Exception $e) {
            // Limpiar flag de procesamiento en caso de error
            unset($processingUuids[$this->originalUuid]);

            Log::error('[AgendarCita::reprogramarCita] Error en reprogramación', [
                'error' => $e->getMessage(),
                'original_uuid' => $this->originalUuid
            ]);

            session()->flash('error', 'Error al reprogramar la cita: ' . $e->getMessage());
        }
    }

    /**
     * Crear nueva cita para reprogramación (evita loop infinito)
     */
    protected function crearNuevaCitaReprogramada(): void
    {
        Log::info('📝 [AgendarCita::crearNuevaCitaReprogramada] Iniciando creación con Jobs (EXACTO como guardarCita)');

        // **PASO 1: PREPARAR DATOS BÁSICOS** (exacto como guardarCita líneas 1640-1672)
        $vehicle = Vehicle::where('license_plate', $this->vehiculo['placa'])->first();
        if (!$vehicle) {
            throw new \Exception('No se encontró el vehículo seleccionado');
        }

        $localSeleccionado = Local::where('code', $this->localSeleccionado)->first();
        if (!$localSeleccionado) {
            throw new \Exception('No se encontró el local seleccionado');
        }

        Log::info("[AgendarCita::crearNuevaCitaReprogramada] Local seleccionado: {$localSeleccionado->name} (ID: {$localSeleccionado->id})");

        // Convertir la fecha de formato DD/MM/YYYY a YYYY-MM-DD (exacto como guardarCita líneas 1676-1681)
        $fechaPartes = explode('/', $this->fechaSeleccionada);
        $fechaFormateada = $fechaPartes[2] . '-' . $fechaPartes[1] . '-' . $fechaPartes[0];

        // Convertir la hora de formato "11:15 AM" a formato "HH:MM:SS" (exacto como guardarCita)
        $horaFormateada = date('H:i', strtotime($this->horaSeleccionada));

        // Obtener el usuario autenticado (exacto como guardarCita línea 1684)
        $user = Auth::user();

        // **VALIDACIONES CRÍTICAS** (exacto como guardarCita líneas 1692-1730)
        if (!$vehicle->brand_code) {
            Log::error('❌ Vehículo sin brand_code', [
                'vehicle_id' => $vehicle->id,
                'license_plate' => $vehicle->license_plate
            ]);
            throw new \Exception('El vehículo no tiene código de marca configurado. Contacte al administrador.');
        }

        // Verificar que existe mapeo organizacional (exacto como guardarCita)
        $mappingExists = CenterOrganizationMapping::mappingExists(
            $localSeleccionado->code,
            $vehicle->brand_code
        );

        if (!$mappingExists) {
            Log::error('❌ No existe mapeo organizacional', [
                'center_code' => $localSeleccionado->code,
                'brand_code' => $vehicle->brand_code
            ]);
            throw new \Exception('No existe configuración organizacional para este centro y marca. Contacte al administrador.');
        }

        Log::info('✅ Validación de mapeo organizacional exitosa', [
            'center_code' => $localSeleccionado->code,
            'brand_code' => $vehicle->brand_code
        ]);

        // **PASO 2: CREAR APPOINTMENT EN BD PRIMERO** (EXACTO como guardarCita líneas 1733-1772)
        $appointment = new Appointment;
        $appointment->appointment_number = 'CITA-' . date('Ymd') . '-' . strtoupper(Str::random(5));
        $appointment->vehicle_id = $vehicle->id;
        $appointment->premise_id = $localSeleccionado->id;
        $appointment->customer_ruc = $user ? $user->document_number : '20605414410';
        $appointment->customer_name = $this->nombreCliente;
        $appointment->customer_last_name = $this->apellidoCliente;
        $appointment->customer_email = $this->emailCliente;
        $appointment->customer_phone = $this->celularCliente;
        $appointment->appointment_date = $fechaFormateada;
        $appointment->appointment_time = $horaFormateada;

        // ✅ CAMPOS ORGANIZACIONALES (exacto como guardarCita)
        $appointment->vehicle_brand_code = $vehicle->brand_code; // Z01, Z02, Z03
        $appointment->center_code = $localSeleccionado->code; // M013, L013, etc.

        // ✅ DETECTAR CLIENTE COMODÍN ANTES DE ASIGNAR PACKAGE_ID
        $isWildcardClient = $user && $user->c4c_internal_id === '1200166011';

        // Solo asignar package_id si NO es cliente comodín
        $appointment->package_id = $isWildcardClient ? null : $this->paqueteId;
        $appointment->vehicle_plate = $vehicle->license_plate; // Para referencia rápida

        // Determinar el service_mode basado en los servicios seleccionados (exacto como guardarCita líneas 1752-1766)
        $serviceModes = [];
        if (in_array('Mantenimiento periódico', $this->serviciosSeleccionados)) {
            $serviceModes[] = 'Mantenimiento periódico';
        }
        if (in_array('Campañas / otros', $this->serviciosSeleccionados)) {
            $serviceModes[] = 'Campañas / otros';
        }
        if (in_array('Reparación', $this->serviciosSeleccionados)) {
            $serviceModes[] = 'Reparación';
        }
        if (in_array('Llamado a revisión', $this->serviciosSeleccionados)) {
            $serviceModes[] = 'Llamado a revisión';
        }

        // ✅ NUEVO: Agregar modalidad Express si está seleccionada
        if ($this->modalidadServicio === 'Mantenimiento Express') {
            $serviceModes[] = 'express';
        }
        $appointment->service_mode = implode(', ', $serviceModes);
        $appointment->maintenance_type = $this->tipoMantenimiento;
        $appointment->comments = $this->comentarios;
        $appointment->status = 'pending'; // Pendiente hasta que C4C confirme
        $appointment->is_synced = false;

        $appointment->save();

        Log::info("[AgendarCita::crearNuevaCitaReprogramada] Appointment creado en BD con ID: {$appointment->id}");

        // **ENVIAR EMAIL DE CITA EDITADA** 📧
        $cambiosRealizados = [
            'Fecha' => [
                'anterior' => $this->originalDate ?? 'No especificada',
                'nuevo' => $this->fechaSeleccionada,
            ],
            'Hora' => [
                'anterior' => $this->originalTime ?? 'No especificada',
                'nuevo' => $this->horaSeleccionada,
            ],
            'Sede' => [
                'anterior' => $this->originalSede ?? 'No especificada',
                'nuevo' => $localSeleccionado->name,
            ],
        ];
        $this->enviarEmailCitaEditada($appointment, $cambiosRealizados);

        // Enviar notificación WhatsApp de reprogramación
        $datosCliente = [
            'nombres' => $this->nombreCliente,
            'apellidos' => $this->apellidoCliente,
            'email' => $this->emailCliente,
            'celular' => $this->celularCliente,
        ];

        $datosVehiculo = [
            'marca' => $this->vehiculo['marca'] ?? 'No especificado',
            'modelo' => $this->vehiculo['modelo'] ?? 'No especificado',
            'placa' => $this->vehiculo['placa'] ?? 'No especificado',
        ];

        app(\App\Services\Notifications\AppointmentWhatsappService::class)->sendAppointmentRescheduled(
            $appointment,
            $datosCliente,
            $datosVehiculo,
            $cambiosRealizados
        );

        Log::info('📧 [CitaEditada] Email de cita editada enviado exitosamente', [
            'appointment_id' => $appointment->id,
            'customer_email' => $appointment->customer_email,
        ]);

        // **PASO 3: GENERAR JOB ID** (exacto como guardarCita línea 1690)
        $this->citaJobId = (string) Str::uuid();

        // **PASO 4: PREPARAR DATOS PARA C4C** (EXACTO como guardarCita líneas 1777-1796)
        $fechaHoraInicio = Carbon::createFromFormat('Y-m-d H:i', $fechaFormateada . ' ' . $horaFormateada);
        $fechaHoraFin = $fechaHoraInicio->copy()->addMinutes(45); // 45 minutos por defecto

        $citaData = [
            'customer_id' => $user->c4c_internal_id ?? '1270002726', // Cliente de prueba si no tiene C4C ID
            'employee_id' => '1740', // ID del asesor por defecto
            'start_date' => $fechaHoraInicio->format('Y-m-d H:i'),
            'end_date' => $fechaHoraFin->format('Y-m-d H:i'),
            'center_id' => $localSeleccionado->code,
            'vehicle_plate' => $vehicle->license_plate,
            'customer_name' => $this->nombreCliente . ' ' . $this->apellidoCliente,
            'notes' => $this->generarComentarioCompleto() ?: null,
            'express' => strpos($appointment->service_mode, 'express') !== false ? 'true' : 'false',
        ];

        $appointmentData = [
            'appointment_number' => $appointment->appointment_number,
            'servicios_adicionales' => $this->serviciosAdicionales,
            'campanas_disponibles' => $this->campanasDisponibles ?? [],
        ];

        // **PASO 5: INICIALIZAR JOB STATUS** (exacto como guardarCita líneas 1798-1804)
        Cache::put("cita_job_{$this->citaJobId}", [
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Preparando envío a C4C...',
            'updated_at' => now(),
        ], 600); // 10 minutos

        // **PASO 6: DESPACHAR JOB EN BACKGROUND** (exacto como guardarCita líneas 1807-1810)
        EnviarCitaC4CJob::dispatch($citaData, $appointmentData, $this->citaJobId, $appointment->id);

        ProcessAppointmentAfterCreationJob::dispatch($appointment->id)
            ->delay(now()->addMinutes(1)); // Delay para que la cita se procese primero

        // **PASO 7: ACTUALIZAR UI INMEDIATAMENTE** (exacto como guardarCita líneas 1813-1815)
        $this->citaStatus = 'processing';
        $this->citaProgress = 0;
        $this->citaMessage = 'Enviando cita a C4C...';

        Log::info('[AgendarCita::crearNuevaCitaReprogramada] Job despachado exitosamente', [
            'job_id' => $this->citaJobId,
            'appointment_id' => $appointment->id,
        ]);

        // **PASO 8: NOTIFICAR AL USUARIO** (exacto como guardarCita líneas 1823-1827)
        \Filament\Notifications\Notification::make()
            ->title('Procesando Cita')
            ->body('Tu cita está siendo procesada. Por favor espera...')
            ->info()
            ->send();

        // **PASO 9: INICIAR POLLING** (exacto como guardarCita línea 1830)
        $this->dispatch('start-polling', jobId: $this->citaJobId);

        // **PASO 10: DESACTIVAR MODO EDICIÓN**
        $this->editMode = false;
        $this->originalUuid = null;
        $this->originalCitaId = null;

        Log::info('🔄 [AgendarCita::crearNuevaCitaReprogramada] Modo edición desactivado - Jobs despachados');
    }

    /**
     * Calcular hora de fin de la cita (agregar 30 minutos)
     */
    protected function calcularHoraFin(): string
    {
        try {
            $horaInicio = \Carbon\Carbon::createFromFormat('H:i', $this->horaSeleccionada);
            return $horaInicio->addMinutes(30)->format('H:i');
        } catch (\Exception $e) {
            return '12:00'; // Fallback
        }
    }

    /**
     * Crear registro en base de datos local para la nueva cita
     */
    protected function crearRegistroLocalReprogramacion(array $createResult): void
    {
        try {
            // Obtener el vehículo de la base de datos
            $vehicle = \App\Models\Vehicle::where('license_plate', $this->vehiculo['placa'])->first();

            if ($vehicle) {
                // Obtener el premise_id del local seleccionado
                $local = \App\Models\Local::where('code', $this->localSeleccionado)->first();
                $premiseId = $local ? $local->id : 1; // Fallback a 1 si no se encuentra

                $appointment = \App\Models\Appointment::create([
                    'customer_id' => $this->datosCliente['id'] ?? 1,
                    'vehicle_id' => $vehicle->id,
                    'premise_id' => $premiseId,
                    'appointment_date' => $this->fechaSeleccionada,
                    'appointment_time' => $this->horaSeleccionada,
                    'status' => 'scheduled',
                    'center_id' => $this->localSeleccionado,
                    'notes' => $this->comentarios ?: "Reprogramación de cita #{$this->originalCitaId}",
                    'c4c_uuid' => $createResult['uuid'] ?? null,
                    'appointment_number' => $createResult['appointment_id'] ?? null
                ]);

                Log::info('[AgendarCita::crearRegistroLocalReprogramacion] Registro local creado', [
                    'appointment_id' => $appointment->id
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[AgendarCita::crearRegistroLocalReprogramacion] Error creando registro local', [
                'error' => $e->getMessage()
            ]);
            // No re-throw - el proceso principal debe continuar
        }
    }

    /**
     * Habilita la edición de datos del cliente
     */
    public function habilitarEdicionDatos(): void
    {
        // Guardar los valores originales para poder cancelar
        $this->nombreClienteOriginal = $this->nombreCliente;
        $this->apellidoClienteOriginal = $this->apellidoCliente;
        $this->emailClienteOriginal = $this->emailCliente;
        $this->celularClienteOriginal = $this->celularCliente;
        
        $this->editandoDatos = true;
        
        Log::info('[AgendarCita] Edición de datos habilitada');
    }

    /**
     * Cancela la edición de datos del cliente
     */
    public function cancelarEdicionDatos(): void
    {
        // Restaurar los valores originales
        $this->nombreCliente = $this->nombreClienteOriginal;
        $this->apellidoCliente = $this->apellidoClienteOriginal;
        $this->emailCliente = $this->emailClienteOriginal;
        $this->celularCliente = $this->celularClienteOriginal;
        
        $this->editandoDatos = false;
        
        Log::info('[AgendarCita] Edición de datos cancelada');
    }

    /**
     * Valida todos los datos del cliente antes de continuar
     */
    protected function validarDatosCliente(): bool
    {
        // Validar que todos los campos estén completos
        if (empty(trim($this->nombreCliente))) {
            \Filament\Notifications\Notification::make()
                ->title('Nombre requerido')
                ->body('Por favor, completa tu nombre antes de continuar.')
                ->danger()
                ->send();
            return false;
        }

        if (empty(trim($this->apellidoCliente))) {
            \Filament\Notifications\Notification::make()
                ->title('Apellido requerido')
                ->body('Por favor, completa tu apellido antes de continuar.')
                ->danger()
                ->send();
            return false;
        }

        if (empty(trim($this->emailCliente)) || !filter_var($this->emailCliente, FILTER_VALIDATE_EMAIL)) {
            \Filament\Notifications\Notification::make()
                ->title('Email inválido')
                ->body('Por favor, ingresa un email válido antes de continuar.')
                ->danger()
                ->send();
            return false;
        }

        // Validar específicamente el celular
        if (!$this->validarCelular()) {
            return false;
        }

        Log::info('[AgendarCita] Validación completa de datos del cliente exitosa');
        return true;
    }

    /**
     * Valida que el número de celular tenga exactamente 9 dígitos
     */
    protected function validarCelular(): bool
    {
        // Limpiar el número de celular (eliminar espacios y caracteres no numéricos)
        $celularLimpio = preg_replace('/[^0-9]/', '', $this->celularCliente);
        
        // Verificar que tenga exactamente 9 dígitos
        if (strlen($celularLimpio) !== 9) {
            \Filament\Notifications\Notification::make()
                ->title('Número de celular inválido')
                ->body('El número de celular debe tener exactamente 9 dígitos. Por favor, edita tus datos y corrige el número.')
                ->danger()
                ->persistent()
                ->send();
            
            Log::warning('[AgendarCita] Validación de celular falló', [
                'celular_original' => $this->celularCliente,
                'celular_limpio' => $celularLimpio,
                'longitud' => strlen($celularLimpio)
            ]);
            
            return false;
        }
        
        // Actualizar el campo con el número limpio
        $this->celularCliente = $celularLimpio;
        
        Log::info('[AgendarCita] Validación de celular exitosa', [
            'celular' => $this->celularCliente
        ]);
        
        return true;
    }

    /**
     * Método que se ejecuta cuando cambia el valor del celular
     * Limpia automáticamente el número para mantener solo dígitos
     */
    public function updatedCelularCliente($value): void
    {
        // Limpiar automáticamente el número (solo dígitos, máximo 9)
        $this->celularCliente = substr(preg_replace('/[^0-9]/', '', $value), 0, 9);
        
        Log::debug('[AgendarCita] Celular actualizado', [
            'valor_original' => $value,
            'valor_limpio' => $this->celularCliente,
            'longitud' => strlen($this->celularCliente)
        ]);
    }

    /**
     * Guarda los datos del cliente editados
     */
    public function guardarDatosCliente(): void
    {
            $this->validate([
                'nombreCliente' => 'required|string|max:255',
                'apellidoCliente' => 'required|string|max:255',
                'emailCliente' => 'required|email|max:255',
                'celularCliente' => ['required', 'digits:9'],
            ], [
                'nombreCliente.required' => 'El nombre es obligatorio',
                'apellidoCliente.required' => 'El apellido es obligatorio',
                'emailCliente.required' => 'El email es obligatorio',
                'emailCliente.email' => 'El email debe tener un formato válido',
                'celularCliente.required' => 'El celular debe tener exactamente 9 números',
            ]);

            $user = Auth::user();
            
            if ($user) {
                $user->update([
                    'name' => trim($this->nombreCliente . ' ' . $this->apellidoCliente),
                    'email' => $this->emailCliente,
                    'phone' => $this->celularCliente,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Datos actualizados')
                    ->body('Tus datos han sido actualizados correctamente.')
                    ->success()
                    ->send();
            }

            $this->editandoDatos = false;

            \Filament\Notifications\Notification::make()
                ->title('Error al actualizar')
                ->body('Hubo un error al actualizar tus datos. Por favor, inténtalo de nuevo.')
                ->danger()
                ->send();
        
    }
}
