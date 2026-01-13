<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Header con botón agregar y filtros -->
        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-4">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-4 md:space-x-4 w-full">
                <!-- Campo de búsqueda -->
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="text"
                        wire:model.live="busqueda"
                        placeholder="Buscar por nombre o código"
                    />
                </x-filament::input.wrapper>

                <!-- Filtro por centro/local -->
                <div class="relative">
                    <select
                        wire:model.live="filtroCentro"
                        class="border border-gray-300 rounded-md pr-6 py-2 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent min-w-48"
                    >
                        <option value="">Todos los locales</option>
                        @foreach($localesDisponibles as $local)
                            <option value="{{ $local->code }}">{{ $local->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Botones de limpiar filtros -->
                @if($busqueda || $filtroCentro)
                    <x-filament::button
                        wire:click="limpiarFiltros"
                        color="gray"
                        size="sm"
                    >
                        Limpiar filtros
                    </x-filament::button>
                @endif
            </div>

            <x-filament::button
                wire:click="agregarServicio"
                color="primary"
            >
                Agregar Servicio
            </x-filament::button>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200">
                <thead class="bg-primary-500">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Nombre
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Código
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Marca
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">
                            Local
                        </th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($this->serviciosPaginados as $servicio)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $servicio['name'] }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $servicio['code'] }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="flex flex-wrap gap-1">
                                    @if(is_array($servicio['brand']))
                                        @foreach($servicio['brand'] as $marca)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($marca === 'Toyota') bg-red-100 text-red-800
                                                @elseif($marca === 'Lexus') bg-blue-100 text-blue-800
                                                @else bg-orange-100 text-orange-800
                                                @endif">
                                                {{ $marca }}
                                            </span>
                                        @endforeach
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            @if($servicio['brand'] === 'Toyota') bg-red-100 text-red-800
                                            @elseif($servicio['brand'] === 'Lexus') bg-blue-100 text-blue-800
                                            @else bg-orange-100 text-orange-800
                                            @endif">
                                            {{ $servicio['brand'] }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                @php
                                    $local = $localesDisponibles->firstWhere('code', $servicio['center_code']);
                                @endphp
                                @if($local)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                                        {{ $local->name }}
                                    </span>
                                @else
                                    <span class="text-gray-400 italic">Sin local asignado</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                <div class="flex justify-center items-center gap-6">
                                    <button
                                        wire:click="editarServicio({{ $servicio['id'] }})"
                                        class="text-primary-600 hover:text-primary-900 flex"
                                        title="Editar"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Editar
                                    </button>

                                    <button
                                        wire:click="eliminarServicio({{ $servicio['id'] }})"
                                        class="text-primary-600 hover:text-primary-900 flex"
                                        title="Eliminar"
                                        onclick="return confirm('¿Estás seguro de que deseas eliminar este servicio adicional?')"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Eliminar
                                    </button>

                                    <div class="inline-flex items-center">
                                        <label class="relative inline-block">
                                            <input
                                                type="checkbox"
                                                id="toggle-{{ $servicio['id'] }}"
                                                wire:click="toggleEstado({{ $servicio['id'] }})"
                                                @if($estadoServicios[$servicio['id']]) checked @endif
                                                class="toggle-checkbox"
                                            />
                                            <span class="toggle-label"></span>
                                        </label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                No se encontraron servicios adicionales
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>

        <!-- Paginación -->
        @if($this->serviciosPaginados->hasPages())
            <div class="flex justify-center">
                <nav class="flex items-center gap-1 sm:gap-2">
                    <!-- Botón Anterior -->
                    @if($this->serviciosPaginados->onFirstPage())
                        <span class="px-2 py-2 text-gray-400 text-sm sm:px-3">
                            <span>‹</span>
                        </span>
                    @else
                        <x-filament::button
                            wire:click="gotoPage({{ $this->serviciosPaginados->currentPage() - 1 }})"
                            color="gray"
                            size="sm"
                            class="px-2 py-2 text-sm sm:px-3"
                        >
                            <span>‹</span>
                        </x-filament::button>
                    @endif

                    @php
                        $currentPage = $this->serviciosPaginados->currentPage();
                        $lastPage = $this->serviciosPaginados->lastPage();
                        $showPages = [];
                        
                        if ($lastPage <= 7) {
                            // Si hay 7 páginas o menos, mostrar todas
                            $showPages = range(1, $lastPage);
                        } else {
                            // Lógica para páginas con puntos suspensivos
                            if ($currentPage <= 4) {
                                // Cerca del inicio: 1 2 3 4 5 ... último
                                $showPages = array_merge(range(1, 5), ['...'], [$lastPage]);
                            } elseif ($currentPage >= $lastPage - 3) {
                                // Cerca del final: 1 ... antepenúltimo penúltimo último
                                $showPages = array_merge([1], ['...'], range($lastPage - 4, $lastPage));
                            } else {
                                // En el medio: 1 ... actual-1 actual actual+1 ... último
                                $showPages = array_merge([1], ['...'], range($currentPage - 1, $currentPage + 1), ['...'], [$lastPage]);
                            }
                        }
                    @endphp

                    @foreach($showPages as $page)
                        @if($page === '...')
                            <span class="px-2 py-2 text-gray-400 text-sm sm:px-3">...</span>
                        @elseif($page == $currentPage)
                            <span class="px-2 py-2 bg-primary-500 text-white rounded text-sm sm:px-3 min-w-[32px] text-center">{{ $page }}</span>
                        @else
                            <x-filament::button
                                wire:click="gotoPage({{ $page }})"
                                color="gray"
                                size="sm"
                                class="px-2 py-2 text-sm sm:px-3 min-w-[32px]"
                            >
                                {{ $page }}
                            </x-filament::button>
                        @endif
                    @endforeach

                    <!-- Botón Siguiente -->
                    @if($this->serviciosPaginados->hasMorePages())
                        <x-filament::button
                            wire:click="gotoPage({{ $this->serviciosPaginados->currentPage() + 1 }})"
                            color="gray"
                            size="sm"
                            class="px-2 py-2 text-sm sm:px-3"
                        >
                            <span>›</span>
                        </x-filament::button>
                    @else
                        <span class="px-2 py-2 text-gray-400 text-sm sm:px-3">
                            <span>›</span>
                        </span>
                    @endif
                </nav>
            </div>
        @endif
    </div>

    <!-- Modal para agregar/editar servicio -->
    @if($isFormModalOpen)
    <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Overlay de fondo oscuro (clic para cerrar) -->
        <div class="fixed inset-0 bg-black/50" aria-hidden="true" wire:click="cerrarFormModal"></div>

        <!-- Contenedor para centrar vertical y horizontalmente -->
        <div class="flex items-center justify-center min-h-screen px-4 py-2 text-center">

            <!-- Modal -->
            <div class="relative inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all max-w-2xl w-full z-[9999]" @click.stop>
                <!-- Contenido del modal -->
                <div class="bg-white px-6 py-4">
                    <!-- Botón de cerrar en la esquina superior derecha -->
                    <div class="absolute top-0 right-0 pt-3 pr-3 z-[10000]">
                        <button type="button" wire:click="cerrarFormModal" class="bg-white rounded-md text-gray-600 hover:text-gray-900 focus:outline-none">
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-col items-center">
                        <h3 class="text-xl font-bold text-primary-600 text-center mb-4" id="modal-title">
                            {{ $accionFormulario === 'crear' ? 'Agregar Servicio Adicional' : 'Editar Servicio Adicional' }}
                        </h3>
                        <div class="w-full">
                            <!-- Layout en 2 columnas para Nombre y Código -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Columna Izquierda -->
                                <div class="space-y-4">
                                    <!-- Campo Nombre -->
                                    <div class="flex flex-col">
                                        <label for="service_name" class="text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                        <input
                                            type="text"
                                            id="service_name"
                                            wire:model="servicioEnEdicion.name"
                                            class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                            placeholder="Nombre del servicio"
                                        >
                                        @error('servicioEnEdicion.name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>

                                <!-- Columna Derecha -->
                                <div class="space-y-4">
                                    <!-- Campo Código -->
                                    <div class="flex flex-col">
                                        <label for="service_code" class="text-sm font-medium text-gray-700 mb-1">Código</label>
                                        <input
                                            type="text"
                                            id="service_code"
                                            wire:model="servicioEnEdicion.code"
                                            class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                            placeholder="Código único"
                                        >
                                        @error('servicioEnEdicion.code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    <!-- Campo Activo -->
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            id="service_active"
                                            wire:model="servicioEnEdicion.is_active"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="service_active" class="ml-2 text-sm text-gray-700">Activo</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Grid de 2 columnas: Local (izquierda) y Marca (derecha) -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <!-- Columna Izquierda: Seleccionar local -->
                                <div class="flex flex-col">
                                    <label for="service_center" class="text-sm font-medium text-gray-700 mb-1">Seleccionar local *</label>
                                    <select
                                        id="service_center"
                                        wire:model="servicioEnEdicion.center_code"
                                        class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    >
                                        <option value="">Seleccione un local</option>
                                        @foreach($localesDisponibles as $local)
                                            <option value="{{ $local->code }}">{{ $local->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('servicioEnEdicion.center_code') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>

                                <!-- Columna Derecha: Seleccionar marca -->
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium text-gray-700 mb-2">Seleccionar marca *</label>
                                    <div class="flex flex-row gap-4 flex-wrap">
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="brand_toyota"
                                                wire:model="servicioEnEdicion.brand"
                                                value="Toyota"
                                                class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                            >
                                            <label for="brand_toyota" class="ml-2 text-sm text-gray-700">Toyota</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="brand_lexus"
                                                wire:model="servicioEnEdicion.brand"
                                                value="Lexus"
                                                class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                            >
                                            <label for="brand_lexus" class="ml-2 text-sm text-gray-700">Lexus</label>
                                        </div>
                                        <div class="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="brand_hino"
                                                wire:model="servicioEnEdicion.brand"
                                                value="Hino"
                                                class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                            >
                                            <label for="brand_hino" class="ml-2 text-sm text-gray-700">Hino</label>
                                        </div>
                                    </div>
                                    @error('servicioEnEdicion.brand') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                    @error('servicioEnEdicion.brand.*') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Campo Días de la Semana (selección múltiple) - Una sola fila horizontal -->
                            <div class="flex flex-col mt-4">
                                <label class="text-sm font-medium text-gray-700 mb-2">Días disponibles *</label>
                                <div class="flex flex-wrap gap-4">
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_monday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="monday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_monday" class="ml-2 text-sm text-gray-700">Lunes</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_tuesday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="tuesday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_tuesday" class="ml-2 text-sm text-gray-700">Martes</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_wednesday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="wednesday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_wednesday" class="ml-2 text-sm text-gray-700">Miércoles</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_thursday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="thursday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_thursday" class="ml-2 text-sm text-gray-700">Jueves</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_friday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="friday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_friday" class="ml-2 text-sm text-gray-700">Viernes</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_saturday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="saturday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_saturday" class="ml-2 text-sm text-gray-700">Sábado</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="day_sunday"
                                            wire:model="servicioEnEdicion.available_days"
                                            value="sunday"
                                            class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                        >
                                        <label for="day_sunday" class="ml-2 text-sm text-gray-700">Domingo</label>
                                    </div>
                                </div>
                                @error('servicioEnEdicion.available_days') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                            </div>

                            <!-- Campo Descripción - Ancho completo -->
                            <div class="flex flex-col mt-4">
                                <label for="service_description" class="text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea
                                    id="service_description"
                                    wire:model="servicioEnEdicion.description"
                                    rows="3"
                                    class="border border-gray-300 rounded-md p-2 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                                    placeholder="Descripción (opcional)"
                                ></textarea>
                            </div>

                            <!-- Botones -->
                            <div class="flex justify-end space-x-2 mt-6 gap-4">
                                <button
                                    type="button"
                                    wire:click="cerrarFormModal"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    wire:click="guardarServicio"
                                    class="px-4 py-2 bg-primary-600 text-white rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                                >
                                    {{ $accionFormulario === 'crear' ? 'Crear' : 'Actualizar' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        /* Estilos para el toggle switch */
        .toggle-checkbox {
            position: absolute;
            opacity: 0;
            height: 0;
            width: 0;
        }

        .toggle-label {
            position: relative;
            display: block;
            height: 24px;
            width: 48px;
            background-color: #ccc;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .toggle-label:after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background-color: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        .toggle-checkbox:checked + .toggle-label {
            background-color: #3b82f6;
        }

        .toggle-checkbox:checked + .toggle-label:after {
            left: calc(100% - 22px);
        }

        .toggle-checkbox:focus + .toggle-label {
            box-shadow: 0 0 1px #3b82f6;
        }
    </style>
</x-filament-panels::page>