<?php

namespace App\Filament\Pages;

use App\Models\Local;
use App\Models\Appointment;
use App\Models\Vehicle;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardKpi extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Dashboard KPI';

    protected static ?string $navigationGroup = 'Reportes & KPIs';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Dashboard de Indicadores';

    protected static string $view = 'filament.pages.dashboard-kpi';

    public string $fechaInicio = '';

    public string $fechaFin = '';

    public string $rangoFechas = '';

    public string $marcaSeleccionada = 'Todos';

    public string $localSeleccionado = 'Todos';

    public string $localSeleccionadoGraficos = 'Todos';

    public string $marcaSeleccionadaGraficos = 'Todos';

    public int $citasGeneradas = 0;

    public int $citasEfectivas = 0;

    public int $porcentajeEfectividad = 0;

    public int $citasDiferidas = 0;

    public int $citasCanceladas = 0;

    public int $citasNoShow = 0;

    public int $citasMantenimiento = 0;

    public int $citasMantenimientoPrepagados = 0;

    public int $porcentajeMantenimiento = 0;

    public int $porcentajePrepagados = 0;

    public int $citasEnTrabajo = 0;

    public int $cantidadUsuarios = 0;

    public array $datosCantidadCitas = [];

    public array $datosTiempoPromedio = [];

    public array $marcas = ['Todos'];

    public array $locales = [];

    public array $localesGraficos = [];

    public function mount(): void
    {
        $fechaFin = now();
        $fechaInicio = now()->subMonth();

        $this->fechaInicio = $fechaInicio->format('d/m/Y');
        $this->fechaFin = $fechaFin->format('d/m/Y');
        $this->rangoFechas = $this->fechaInicio.' - '.$this->fechaFin;
        $this->cargarLocales();
        $this->cargarMarcas();
        $this->actualizarLocalesPorMarcaGraficos();
        $this->cargarDatos();
    }

    protected function cargarLocales(): void
    {
        try {
            $this->actualizarLocalesPorMarca();
        } catch (\Exception $e) {
            $this->locales = ['Todos' => 'Todos'];
        }
    }

    protected function actualizarLocalesPorMarca(): void
    {
        $query = Local::where('is_active', true);
        
        if ($this->marcaSeleccionada !== 'Todos') {
            $query->where('brand', $this->marcaSeleccionada);
        }
        
        $localesActivos = $query->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        $this->locales = ['Todos' => 'Todos'] + $localesActivos;
        
        if ($this->localSeleccionado !== 'Todos' && !array_key_exists($this->localSeleccionado, $this->locales)) {
            $this->localSeleccionado = 'Todos';
        }
    }

    protected function actualizarLocalesPorMarcaGraficos(): void
    {
        $query = Local::where('is_active', true);
        
        if ($this->marcaSeleccionadaGraficos !== 'Todos') {
            $query->where('brand', $this->marcaSeleccionadaGraficos);
        }
        
        $localesActivos = $query->orderBy('name')
            ->pluck('name', 'code')
            ->toArray();

        $this->localesGraficos = ['Todos' => 'Todos'] + $localesActivos;
        
        if ($this->localSeleccionadoGraficos !== 'Todos' && !array_key_exists($this->localSeleccionadoGraficos, $this->localesGraficos)) {
            $this->localSeleccionadoGraficos = 'Todos';
        }
    }

    protected function cargarMarcas(): void
    {
        try {
            $marcasDb = Vehicle::distinct('brand_name')
                ->whereNotNull('brand_name')
                ->where('brand_name', '!=', '')
                ->pluck('brand_name')
                ->sort()
                ->values()
                ->toArray();

            $this->marcas = array_merge(['Todos'], $marcasDb);

            if (empty($marcasDb)) {
                $this->marcas = ['Todos', 'Toyota', 'Lexus', 'Hino'];
            }

            $this->marcaSeleccionada = 'Todos';
            $this->marcaSeleccionadaGraficos = 'Todos';

        } catch (\Exception $e) {
            $this->marcas = ['Todos', 'Toyota', 'Lexus', 'Hino'];
            $this->marcaSeleccionada = 'Todos';
            $this->marcaSeleccionadaGraficos = 'Todos';
        }
    }

    public function cargarDatos(): void
    {
        try {
            if (empty($this->fechaInicio) || empty($this->fechaFin)) {
                $this->establecerValoresPorDefecto();
                return;
            }

            $fechaInicioCarbon = $this->parsearFecha($this->fechaInicio);
            $fechaFinCarbon = $this->parsearFecha($this->fechaFin);

            $query = $this->construirQueryBase($fechaInicioCarbon, $fechaFinCarbon);

            $this->calcularKpisPrincipales($query);

            $this->calcularCantidadUsuarios($fechaInicioCarbon, $fechaFinCarbon);

            $this->cargarDatosGraficos($fechaInicioCarbon, $fechaFinCarbon);

            $this->js("
                if (typeof window.updateAllCharts === 'function') {
                    window.updateAllCharts({
                        porcentajeMantenimiento: {$this->porcentajeMantenimiento},
                        porcentajePrepagados: {$this->porcentajePrepagados},
                        datosCantidadCitas: " . json_encode($this->datosCantidadCitas) . "
                    });
                }
            ");

        } catch (\Exception $e) {
            $this->establecerValoresPorDefecto();
        }
    }

    protected function parsearFecha(string $fecha): Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y', $fecha)->startOfDay();
        } catch (\Exception $e) {
            return Carbon::now()->startOfDay();
        }
    }

    protected function construirQueryBase(Carbon $fechaInicio, Carbon $fechaFin)
    {
        $query = Appointment::query()
            ->whereBetween('appointment_date', [$fechaInicio->format('Y-m-d'), $fechaFin->format('Y-m-d')]);

        if ($this->localSeleccionado !== 'Todos') {
            $localId = $this->obtenerIdLocal($this->localSeleccionado);
            if ($localId) {
                $query->where('premise_id', $localId);
            }
        }

        if ($this->marcaSeleccionada !== 'Todos') {
            $query->whereHas('vehicle', function ($q) {
                $q->where('brand_name', 'like', '%' . $this->marcaSeleccionada . '%');
            });
        }

        return $query;
    }

    protected function calcularKpisPrincipales($query): void
    {
        $this->citasGeneradas = (clone $query)
            ->where('status', 'confirmed')
            ->where(function($q) {
                $q->whereNull('comments')
                  ->orWhere('comments', 'NOT LIKE', '%prueba%');
            })
            ->count();

        $this->citasEnTrabajo = (clone $query)
            ->where('status', 'confirmed')
            ->where(function($q) {
                $q->whereRaw("JSON_EXTRACT(frontend_states, '$.en_trabajo.activo') = true")
                  ->orWhereRaw("JSON_EXTRACT(frontend_states, '$.en_trabajo.completado') = true")
                  ->orWhereRaw("JSON_EXTRACT(frontend_states, '$.trabajo_concluido.activo') = true")
                  ->orWhereRaw("JSON_EXTRACT(frontend_states, '$.trabajo_concluido.completado') = true");
            })
            ->count();

        $this->citasCanceladas = (clone $query)
            ->where('status', 'cancelled')
            ->where('rescheduled', 0)
            ->count();

        $this->citasNoShow = (clone $query)
            ->where('status', 'confirmed')
            ->noShow()
            ->count();

        $this->porcentajeEfectividad = $this->citasGeneradas > 0 
            ? round(($this->citasEnTrabajo / $this->citasGeneradas) * 100) 
            : 0;

        // Citas con mantenimiento (de las citas generadas)
        $citasConMantenimiento = (clone $query)
            ->where('status', 'confirmed')
            ->where(function($q) {
                $q->whereNull('comments')
                  ->orWhere('comments', 'NOT LIKE', '%prueba%');
            })
            ->whereNotNull('maintenance_type')
            ->where('maintenance_type', '!=', '')
            ->count();

        $this->citasMantenimiento = $citasConMantenimiento;
        $this->porcentajeMantenimiento = $this->citasGeneradas > 0 
            ? round(($citasConMantenimiento / $this->citasGeneradas) * 100) 
            : 0;

        $this->citasDiferidas = (clone $query)->where('rescheduled', 1)->count();

        // Citas sin mantenimiento (citas generadas - citas con mantenimiento)
        $this->citasMantenimientoPrepagados = $this->citasGeneradas - $citasConMantenimiento;
        $this->porcentajePrepagados = $this->citasGeneradas > 0 
            ? round(($this->citasMantenimientoPrepagados / $this->citasGeneradas) * 100) 
            : 0;
    }

    protected function calcularCantidadUsuarios(Carbon $fechaInicio = null, Carbon $fechaFin = null): void
    {
        try {
            $query = User::role('Usuario');
            
            if ($fechaInicio && $fechaFin) {
                $query->whereBetween('created_at', [
                    $fechaInicio->format('Y-m-d 00:00:00'),
                    $fechaFin->format('Y-m-d 23:59:59')
                ]);
            }
            
            $this->cantidadUsuarios = $query->count();
            
        } catch (\Exception $e) {
            $this->cantidadUsuarios = 0;
        }
    }

    protected function cargarDatosGraficos(Carbon $fechaInicio, Carbon $fechaFin): void
    {
        $queryGraficos = $this->construirQueryBaseGraficos($fechaInicio, $fechaFin);

        $diasDiferencia = $fechaInicio->diffInDays($fechaFin);
        
        $this->cargarDatosPorDia($queryGraficos, $fechaInicio, $fechaFin);
    }

    protected function cargarDatosPorSemana($query, Carbon $fechaInicio, Carbon $fechaFin): void
    {
        $datosPorSemana = $query
            ->select(
                DB::raw('YEAR(appointment_date) as year'),
                DB::raw('WEEK(appointment_date, 1) as week'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as efectivas')
            )
            ->groupBy('year', 'week')
            ->orderBy('year')
            ->orderBy('week')
            ->get();

        $labels = [];
        $generadas = [];
        $efectivas = [];
        
        $fechaActual = $fechaInicio->copy()->startOfWeek();
        while ($fechaActual <= $fechaFin) {
            $year = $fechaActual->year;
            $week = $fechaActual->week;
            
            $datoSemana = $datosPorSemana->first(function ($item) use ($year, $week) {
                return $item->year == $year && $item->week == $week;
            });
            
            $labels[] = 'Sem ' . $fechaActual->format('d/m');
            $generadas[] = $datoSemana ? $datoSemana->total : 0;
            $efectivas[] = $datoSemana ? $datoSemana->efectivas : 0;
            
            $fechaActual->addWeek();
        }

        $porcentajesEfectividad = [];
        for ($i = 0; $i < count($generadas); $i++) {
            $porcentajesEfectividad[] = $generadas[$i] > 0 
                ? round(($efectivas[$i] / $generadas[$i]) * 100, 1) 
                : 0;
        }

        $this->datosCantidadCitas = [
            'labels' => $labels,
            'generadas' => $generadas,
            'efectivas' => $efectivas,
            'porcentajesEfectividad' => $porcentajesEfectividad,
        ];
    }

    protected function cargarDatosPorDia($query, Carbon $fechaInicio, Carbon $fechaFin): void
    {
        $datosPorDia = $query
            ->select(
                DB::raw('DATE(appointment_date) as fecha'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(
                    CASE 
                        WHEN status = "confirmed"
                        AND (
                            JSON_EXTRACT(frontend_states, "$.en_trabajo.activo") = true
                            OR JSON_EXTRACT(frontend_states, "$.en_trabajo.completado") = true
                        )
                        THEN 1 ELSE 0
                    END
                ) as efectivas')
            )
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->keyBy('fecha'); 

        $labels = [];
        $generadas = [];
        $efectivas = [];
        $porcentajesEfectividad = [];
        
        $fechaActual = $fechaInicio->copy();
        while ($fechaActual <= $fechaFin) {
            $fechaStr = $fechaActual->format('Y-m-d');
            
            $datoDia = $datosPorDia->get($fechaStr);
            
            $citasGeneradasDia = $datoDia ? $datoDia->total : 0;
            $citasEfectivasDia = $datoDia ? $datoDia->efectivas : 0;
            
            $porcentajeEfectividadDia = $citasGeneradasDia > 0 
                ? round(($citasEfectivasDia / $citasGeneradasDia) * 100, 1) 
                : 0;
            
            $labels[] = $fechaActual->format('d/m');
            $generadas[] = $citasGeneradasDia;
            $efectivas[] = $citasEfectivasDia;
            $porcentajesEfectividad[] = $porcentajeEfectividadDia;
            
            $fechaActual->addDay();
        }

        $this->datosCantidadCitas = [
            'labels' => $labels,
            'generadas' => $generadas,
            'efectivas' => $efectivas,
            'porcentajesEfectividad' => $porcentajesEfectividad,
        ];
    }

    protected function cargarDatosPorMes($query, Carbon $fechaInicio, Carbon $fechaFin): void
    {
        $datosPorMes = $query
            ->select(
                DB::raw('YEAR(appointment_date) as year'),
                DB::raw('MONTH(appointment_date) as month'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as efectivas')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $labels = [];
        $generadas = [];
        $efectivas = [];
        
        $fechaActual = $fechaInicio->copy()->startOfMonth();
        while ($fechaActual <= $fechaFin) {
            $year = $fechaActual->year;
            $month = $fechaActual->month;
            
            $datoMes = $datosPorMes->first(function ($item) use ($year, $month) {
                return $item->year == $year && $item->month == $month;
            });
            
            $labels[] = $fechaActual->format('M Y');
            $generadas[] = $datoMes ? $datoMes->total : 0;
            $efectivas[] = $datoMes ? $datoMes->efectivas : 0;
            
            $fechaActual->addMonth();
        }

        $porcentajesEfectividad = [];
        for ($i = 0; $i < count($generadas); $i++) {
            $porcentajesEfectividad[] = $generadas[$i] > 0 
                ? round(($efectivas[$i] / $generadas[$i]) * 100, 1) 
                : 0;
        }

        $this->datosCantidadCitas = [
            'labels' => $labels,
            'generadas' => $generadas,
            'efectivas' => $efectivas,
            'porcentajesEfectividad' => $porcentajesEfectividad,
        ];
    }

    protected function cargarDatosPorTrimestre($query, Carbon $fechaInicio, Carbon $fechaFin): void
    {
        $datosPorTrimestre = $query
            ->select(
                DB::raw('YEAR(appointment_date) as year'),
                DB::raw('QUARTER(appointment_date) as quarter'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as efectivas')
            )
            ->groupBy('year', 'quarter')
            ->orderBy('year')
            ->orderBy('quarter')
            ->get();

        $labels = [];
        $generadas = [];
        $efectivas = [];
        
        $fechaActual = $fechaInicio->copy()->startOfQuarter();
        while ($fechaActual <= $fechaFin) {
            $year = $fechaActual->year;
            $quarter = $fechaActual->quarter;
            
            $datoTrimestre = $datosPorTrimestre->first(function ($item) use ($year, $quarter) {
                return $item->year == $year && $item->quarter == $quarter;
            });
            
            $labels[] = 'Q' . $quarter . ' ' . $year;
            $generadas[] = $datoTrimestre ? $datoTrimestre->total : 0;
            $efectivas[] = $datoTrimestre ? $datoTrimestre->efectivas : 0;
            
            $fechaActual->addQuarter();
        }

        $porcentajesEfectividad = [];
        for ($i = 0; $i < count($generadas); $i++) {
            $porcentajesEfectividad[] = $generadas[$i] > 0 
                ? round(($efectivas[$i] / $generadas[$i]) * 100, 1) 
                : 0;
        }

        $this->datosCantidadCitas = [
            'labels' => $labels,
            'generadas' => $generadas,
            'efectivas' => $efectivas,
            'porcentajesEfectividad' => $porcentajesEfectividad,
        ];
    }

    protected function construirQueryBaseGraficos(Carbon $fechaInicio, Carbon $fechaFin)
    {
        $query = Appointment::query()
            ->whereBetween('appointment_date', [$fechaInicio->format('Y-m-d'), $fechaFin->format('Y-m-d')]);

        if ($this->localSeleccionadoGraficos !== 'Todos') {
            $localId = $this->obtenerIdLocal($this->localSeleccionadoGraficos);
            if ($localId) {
                $query->where('premise_id', $localId);
            }
        }

        if ($this->marcaSeleccionadaGraficos !== 'Todos') {
            $query->whereHas('vehicle', function ($q) {
                $q->where('brand_name', 'like', '%' . $this->marcaSeleccionadaGraficos . '%');
            });
        }

        return $query;
    }

    protected function obtenerIdLocal(string $codigoLocal): ?int
    {
        try {
            $local = Local::where('code', $codigoLocal)->first();
            return $local ? $local->id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function establecerValoresPorDefecto(): void
    {
        $this->citasGeneradas = 0;
        $this->citasEfectivas = 0;
        $this->porcentajeEfectividad = 0;
        $this->citasDiferidas = 0;
        $this->citasCanceladas = 0;
        $this->citasNoShow = 0;
        $this->citasMantenimiento = 0;
        $this->citasMantenimientoPrepagados = 0;
        $this->porcentajeMantenimiento = 0;
        $this->porcentajePrepagados = 0;
        $this->calcularCantidadUsuarios();
        $this->datosCantidadCitas = ['labels' => [], 'generadas' => [], 'efectivas' => [], 'porcentajesEfectividad' => []];
        $this->datosTiempoPromedio = ['labels' => [], 'tiempos' => []];
    }

    public function aplicarFiltros(): void
    {
        if (! empty($this->rangoFechas)) {
            $fechas = [];
            if (strpos($this->rangoFechas, ' - ') !== false) {
                $fechas = explode(' - ', $this->rangoFechas);
            } elseif (strpos($this->rangoFechas, ' a ') !== false) {
                $fechas = explode(' a ', $this->rangoFechas);
            } elseif (strpos($this->rangoFechas, ' to ') !== false) {
                $fechas = explode(' to ', $this->rangoFechas);
            }
            
            if (count($fechas) === 2) {
                $this->fechaInicio = trim($fechas[0]);
                $this->fechaFin = trim($fechas[1]);
                $this->rangoFechas = $this->fechaInicio . ' - ' . $this->fechaFin;
            } else {
                return;
            }
        }

        $this->cargarDatos();
    }

    public function updatedLocalSeleccionado(): void
    {
        $this->cargarDatos();
    }

    public function updatedMarcaSeleccionada(): void
    {
        $this->actualizarLocalesPorMarca();
        
        $this->cargarDatos();
    }

    public function updatedLocalSeleccionadoGraficos(): void
    {
        $this->cargarDatos();
    }

    public function updatedMarcaSeleccionadaGraficos(): void
    {
        $this->actualizarLocalesPorMarcaGraficos();
        
        $this->cargarDatos();
    }

    public function updatedRangoFechas(): void
    {
        if (empty($this->rangoFechas)) {
            return;
        }
        
        if (!strpos($this->rangoFechas, ' - ') && !strpos($this->rangoFechas, ' a ') && !strpos($this->rangoFechas, ' to ')) {
            return;
        }
        
        $rangoAnterior = $this->fechaInicio . ' - ' . $this->fechaFin;
        if ($this->rangoFechas === $rangoAnterior) {
            return;
        }
        
        $this->aplicarFiltros();
    }

    public function updatedFechaInicio(): void
    {
        $this->cargarDatos();
    }

    public function updatedFechaFin(): void
    {
        $this->cargarDatos();
    }

    public function getChartData(): array
    {
        return [
            'porcentajeMantenimiento' => $this->porcentajeMantenimiento,
            'porcentajePrepagados' => $this->porcentajePrepagados,
            'datosCantidadCitas' => $this->datosCantidadCitas,
        ];
    }
}
