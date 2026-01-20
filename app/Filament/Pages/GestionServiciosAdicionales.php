<?php

namespace App\Filament\Pages;

use App\Models\AdditionalService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class GestionServiciosAdicionales extends Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?string $navigationLabel = 'Otros Servicios';

    protected static ?string $title = 'Otros Servicios';

    protected static string $view = 'filament.pages.gestion-servicios-adicionales';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    // Propiedades para la tabla
    public Collection $serviciosAdicionales;

    public int $perPage = 10;

    public int $currentPage = 1;

    // Propiedad para búsqueda
    public string $busqueda = '';

    // Propiedad para filtro de centro
    public string $filtroCentro = '';

    // Propiedad para almacenar los locales/centros disponibles
    public Collection $localesDisponibles;

    // Estado de los servicios
    public array $estadoServicios = [];

    // Modal para agregar/editar servicio
    public bool $isFormModalOpen = false;

    public ?array $servicioEnEdicion = null;

    public string $accionFormulario = 'crear';

    public function mount(): void
    {
        $this->currentPage = request()->query('page', 1);
        $this->cargarLocalesDisponibles();
        $this->cargarServiciosAdicionales();
    }

    public function cargarLocalesDisponibles(): void
    {
        try {
            $this->localesDisponibles = \App\Models\Local::where('is_active', true)
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al cargar locales')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
            $this->localesDisponibles = collect();
        }
    }

    public function cargarServiciosAdicionales(): void
    {
        try {
            $servicios = AdditionalService::orderBy('name')->get();

            $this->serviciosAdicionales = $servicios->map(function ($servicio) {
                // Asegurar que brand siempre sea un array
                $brand = $servicio->brand;
                if (!is_array($brand)) {
                    $brand = $brand ? [$brand] : ['Toyota'];
                }

                // Asegurar que available_days siempre sea un array
                $availableDays = $servicio->available_days;
                if (!is_array($availableDays)) {
                    $availableDays = $availableDays ? [$availableDays] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                }

                // Asegurar que center_code siempre sea un array
                $centerCode = $servicio->center_code;
                if (!is_array($centerCode)) {
                    $centerCode = $centerCode ? [$centerCode] : [];
                }

                return [
                    'id' => $servicio->id,
                    'name' => $servicio->name,
                    'code' => $servicio->code,
                    'brand' => $brand,
                    'center_code' => $centerCode,
                    'available_days' => $availableDays,
                    'description' => $servicio->description,
                    'is_active' => $servicio->is_active,
                ];
            });

            // Inicializar el estado de los servicios
            foreach ($this->serviciosAdicionales as $servicio) {
                $this->estadoServicios[$servicio['id']] = $servicio['is_active'];
            }
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al cargar servicios adicionales')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
            $this->serviciosAdicionales = collect();
        }
    }

    public function getServiciosPaginadosProperty(): LengthAwarePaginator
    {
        $serviciosFiltrados = $this->serviciosAdicionales;

        // Filtro por búsqueda de texto
        if (! empty($this->busqueda)) {
            $terminoBusqueda = strtolower($this->busqueda);
            $serviciosFiltrados = $serviciosFiltrados->filter(function ($servicio) use ($terminoBusqueda) {
                return str_contains(strtolower($servicio['name']), $terminoBusqueda) ||
                    str_contains(strtolower($servicio['code']), $terminoBusqueda);
            });
        }

        // Filtro por centro
        if (! empty($this->filtroCentro)) {
            $serviciosFiltrados = $serviciosFiltrados->filter(function ($servicio) {
                return is_array($servicio['center_code']) && in_array($this->filtroCentro, $servicio['center_code']);
            });
        }

        if ($serviciosFiltrados->count() > 0 && $this->currentPage > ceil($serviciosFiltrados->count() / $this->perPage)) {
            $this->currentPage = 1;
        }

        return new LengthAwarePaginator(
            $serviciosFiltrados->forPage($this->currentPage, $this->perPage),
            $serviciosFiltrados->count(),
            $this->perPage,
            $this->currentPage,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    public function toggleEstado(int $id): void
    {
        try {
            $this->estadoServicios[$id] = ! $this->estadoServicios[$id];

            $this->serviciosAdicionales = $this->serviciosAdicionales->map(function ($servicio) use ($id) {
                if ($servicio['id'] === $id) {
                    $servicio['is_active'] = $this->estadoServicios[$id];
                }

                return $servicio;
            });

            $servicio = AdditionalService::findOrFail($id);
            $servicio->is_active = $this->estadoServicios[$id];
            $servicio->save();

            $estado = $this->estadoServicios[$id] ? 'activado' : 'desactivado';
            \Filament\Notifications\Notification::make()
                ->title('Estado actualizado')
                ->body("El servicio adicional ha sido {$estado}")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al actualizar estado')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
            $this->estadoServicios[$id] = ! $this->estadoServicios[$id];
            $this->cargarServiciosAdicionales();
        }
    }

    public function agregarServicio(): void
    {
        $this->accionFormulario = 'crear';
        $this->servicioEnEdicion = [
            'id' => null,
            'name' => '',
            'code' => '',
            'brand' => ['Toyota'], // Inicializar con Toyota por defecto
            'center_code' => [], // Array de códigos de centro (múltiple)
            'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'], // Todos los días por defecto
            'description' => '',
            'is_active' => true,
        ];
        $this->isFormModalOpen = true;
    }

    public function editarServicio(int $id): void
    {
        try {
            // Always fetch fresh data from database to avoid inconsistencies
            $servicioModel = AdditionalService::findOrFail($id);

            $this->accionFormulario = 'editar';
            $this->servicioEnEdicion = [
                'id' => $servicioModel->id,
                'name' => $servicioModel->name,
                'code' => $servicioModel->code,
                'brand' => is_array($servicioModel->brand) ? $servicioModel->brand : ($servicioModel->brand ? [$servicioModel->brand] : ['Toyota']),
                'center_code' => is_array($servicioModel->center_code) ? $servicioModel->center_code : ($servicioModel->center_code ? [$servicioModel->center_code] : []),
                'available_days' => is_array($servicioModel->available_days) ? $servicioModel->available_days : ($servicioModel->available_days ? [$servicioModel->available_days] : ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'description' => $servicioModel->description,
                'is_active' => $servicioModel->is_active,
            ];

            $this->isFormModalOpen = true;
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al editar servicio')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function guardarServicio(): void
    {
        try {
            // Asegurar que brand sea un array
            if (!is_array($this->servicioEnEdicion['brand'])) {
                $this->servicioEnEdicion['brand'] = [];
            }

            // Asegurar que available_days sea un array
            if (!is_array($this->servicioEnEdicion['available_days'])) {
                $this->servicioEnEdicion['available_days'] = [];
            }

            // Asegurar que center_code sea un array
            if (!is_array($this->servicioEnEdicion['center_code'])) {
                $this->servicioEnEdicion['center_code'] = [];
            }

            // Filtrar valores vacíos y duplicados
            $this->servicioEnEdicion['brand'] = array_values(array_unique(array_filter($this->servicioEnEdicion['brand'])));
            $this->servicioEnEdicion['center_code'] = array_values(array_unique(array_filter($this->servicioEnEdicion['center_code'])));
            $this->servicioEnEdicion['available_days'] = array_values(array_unique(array_filter($this->servicioEnEdicion['available_days'])));

            // Validación adicional manual
            if (empty($this->servicioEnEdicion['brand'])) {
                throw new \Exception('Debe seleccionar al menos una marca');
            }

            if (empty($this->servicioEnEdicion['center_code'])) {
                throw new \Exception('Debe seleccionar al menos un local/centro');
            }

            if (empty($this->servicioEnEdicion['available_days'])) {
                throw new \Exception('Debe seleccionar al menos un día de la semana');
            }

            // Verificar que las marcas sean válidas
            foreach ($this->servicioEnEdicion['brand'] as $marca) {
                if (!in_array($marca, ['Toyota', 'Lexus', 'Hino'])) {
                    throw new \Exception("La marca '{$marca}' no es válida");
                }
            }

            // Verificar que los centros sean válidos
            $validCenterCodes = \App\Models\Local::where('is_active', true)
                ->pluck('code')
                ->toArray();

            foreach ($this->servicioEnEdicion['center_code'] as $centerCode) {
                if (!in_array($centerCode, $validCenterCodes)) {
                    throw new \Exception("El centro '{$centerCode}' no es válido");
                }
            }

            // Verificar que los días sean válidos
            $validDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($this->servicioEnEdicion['available_days'] as $day) {
                if (!in_array($day, $validDays)) {
                    throw new \Exception("El día '{$day}' no es válido");
                }
            }

            $this->validate([
                'servicioEnEdicion.name' => 'required|string|max:255',
                'servicioEnEdicion.code' => 'required|string|max:50',
                'servicioEnEdicion.brand' => 'required|array|min:1',
                'servicioEnEdicion.brand.*' => 'in:Toyota,Lexus,Hino',
                'servicioEnEdicion.center_code' => 'required|array|min:1',
                'servicioEnEdicion.available_days' => 'required|array|min:1',
            ], [
                'servicioEnEdicion.name.required' => 'El nombre es obligatorio',
                'servicioEnEdicion.code.required' => 'El código es obligatorio',
                'servicioEnEdicion.brand.required' => 'Debe seleccionar al menos una marca',
                'servicioEnEdicion.brand.min' => 'Debe seleccionar al menos una marca',
                'servicioEnEdicion.brand.*.in' => 'Las marcas deben ser Toyota, Lexus o Hino',
                'servicioEnEdicion.center_code.required' => 'Debe seleccionar al menos un local/centro',
                'servicioEnEdicion.center_code.min' => 'Debe seleccionar al menos un local/centro',
                'servicioEnEdicion.available_days.required' => 'Debe seleccionar al menos un día',
                'servicioEnEdicion.available_days.min' => 'Debe seleccionar al menos un día',
            ]);

            if ($this->accionFormulario === 'editar' && ! empty($this->servicioEnEdicion['id'])) {
                $servicio = AdditionalService::findOrFail($this->servicioEnEdicion['id']);
            } else {
                $servicio = new AdditionalService;
            }

            $servicio->name = $this->servicioEnEdicion['name'];
            $servicio->code = $this->servicioEnEdicion['code'];
            $servicio->brand = $this->servicioEnEdicion['brand'];
            $servicio->center_code = $this->servicioEnEdicion['center_code'];
            $servicio->available_days = $this->servicioEnEdicion['available_days'];
            $servicio->description = $this->servicioEnEdicion['description'] ?? null;
            $servicio->is_active = $this->servicioEnEdicion['is_active'] ?? true;
            $servicio->save();

            \Filament\Notifications\Notification::make()
                ->title('Servicio guardado')
                ->body('El servicio adicional ha sido guardado correctamente')
                ->success()
                ->send();

            $this->isFormModalOpen = false;
            $this->cargarServiciosAdicionales();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al guardar servicio')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function eliminarServicio(int $id): void
    {
        try {
            $servicio = AdditionalService::findOrFail($id);
            $nombreServicio = $servicio->name;
            $servicio->delete();

            // Recargar la lista
            $this->cargarServiciosAdicionales();

            \Filament\Notifications\Notification::make()
                ->title('Servicio eliminado')
                ->body("El servicio adicional '{$nombreServicio}' ha sido eliminado correctamente")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Error al eliminar servicio')
                ->body('Ha ocurrido un error: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cerrarFormModal(): void
    {
        $this->isFormModalOpen = false;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
        $this->currentPage = 1;
    }

    public function limpiarFiltroCentro(): void
    {
        $this->filtroCentro = '';
        $this->currentPage = 1;
    }

    public function limpiarFiltros(): void
    {
        $this->busqueda = '';
        $this->filtroCentro = '';
        $this->currentPage = 1;
    }

    public function updatedBusqueda(): void
    {
        $this->currentPage = 1;
    }

    public function updatedFiltroCentro(): void
    {
        $this->currentPage = 1;
    }

    public function gotoPage(int $page): void
    {
        $this->currentPage = $page;
    }

    /**
     * Método para manejar la selección/deselección de días
     */
    public function toggleDia(string $day): void
    {
        // Asegurar que available_days sea un array
        if (!is_array($this->servicioEnEdicion['available_days'])) {
            $this->servicioEnEdicion['available_days'] = [];
        }

        // Toggle del día
        if (in_array($day, $this->servicioEnEdicion['available_days'])) {
            // Remover el día
            $this->servicioEnEdicion['available_days'] = array_values(array_filter($this->servicioEnEdicion['available_days'], function($d) use ($day) {
                return $d !== $day;
            }));
        } else {
            // Agregar el día
            $this->servicioEnEdicion['available_days'][] = $day;
        }

        // Limpiar duplicados y reindexar
        $this->servicioEnEdicion['available_days'] = array_values(array_unique($this->servicioEnEdicion['available_days']));
    }

    /**
     * Verificar si un día está seleccionado
     */
    public function isDiaSeleccionado(string $day): bool
    {
        return is_array($this->servicioEnEdicion['available_days'] ?? []) && in_array($day, $this->servicioEnEdicion['available_days']);
    }

    /**
     * Método para manejar la selección/deselección de locales
     */
    public function toggleLocal(string $centerCode): void
    {
        // Asegurar que center_code sea un array
        if (!is_array($this->servicioEnEdicion['center_code'])) {
            $this->servicioEnEdicion['center_code'] = [];
        }

        // Toggle del local
        if (in_array($centerCode, $this->servicioEnEdicion['center_code'])) {
            // Remover el local
            $this->servicioEnEdicion['center_code'] = array_values(array_filter($this->servicioEnEdicion['center_code'], function($c) use ($centerCode) {
                return $c !== $centerCode;
            }));
        } else {
            // Agregar el local
            $this->servicioEnEdicion['center_code'][] = $centerCode;
        }

        // Limpiar duplicados y reindexar
        $this->servicioEnEdicion['center_code'] = array_values(array_unique($this->servicioEnEdicion['center_code']));
    }

    /**
     * Verificar si un local está seleccionado
     */
    public function isLocalSeleccionado(string $centerCode): bool
    {
        return is_array($this->servicioEnEdicion['center_code'] ?? []) && in_array($centerCode, $this->servicioEnEdicion['center_code']);
    }
}
