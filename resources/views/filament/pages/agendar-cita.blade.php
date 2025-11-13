<x-filament-panels::page>
    <style>
        [x-cloak] { display: none !important; }
        
        /* ✅ SMART: Estilos para horarios no disponibles */
        .horario-no-disponible {
            opacity: 0.6;
            background-color: #f3f4f6 !important; /* bg-gray-100 */
            color: #9ca3af !important; /* text-gray-400 */
            cursor: not-allowed !important;
            border: 1px solid #e5e7eb; /* border-gray-200 */
        }
        
        .horario-no-disponible:hover {
            background-color: #f3f4f6 !important; /* No cambiar en hover */
            border-color: #e5e7eb !important; /* No cambiar border en hover */
        }
        
        /* ✅ YAGNI: Estilos adicionales para mejor UX */
        .horario-card {
            transition: all 0.2s ease;
            user-select: none;
        }
    </style>
    <div class="bg-white rounded-lg shadow-sm p-6">
        <!-- Indicador de progreso -->
        <div class="flex justify-center items-center mb-4">
            <div class="flex items-center">
                @for ($i = 1; $i <= $totalPasos; $i++)
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $pasoActual >= $i ? 'bg-primary-600 text-white' : 'bg-gray-300 text-gray-700' }} border border-gray-400">
                            {{ $i }}
                        </div>
                        @if ($i < $totalPasos)
                            <div class="h-1 w-16 {{ $pasoActual > $i ? 'bg-primary-600' : 'bg-gray-300' }} mx-2"></div>
                        @endif 
                    </div>
                @endfor
            </div>
        </div>

        <!-- Banner para modo edición -->
        @if($editMode)
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-amber-600 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h3 class="text-amber-800 font-medium">Reprogramando Cita</h3>
                        <div class="text-amber-700 text-sm mt-1">
                            <p><strong>Cita Original:</strong> {{ $originalDate ?? 'N/A' }} a las {{ $originalTime ?? 'N/A' }}</p>
                            <p><strong>Centro:</strong> {{ $originalSede ?? 'N/A' }}</p>
                            <p><strong>Servicio:</strong> {{ $originalServicio ?? 'N/A' }}</p>
                            <p class="mt-2 text-amber-600">Selecciona una nueva fecha y hora para reprogramar tu cita.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <h1 class="text-2xl font-bold text-primary-600 mb-6">{{ $editMode ? 'Reprogramar Cita' : 'Datos de la cita' }}</h1>
        <br>

        <!-- Paso 1: Formulario de datos -->
        <div class="{{ $pasoActual == 1 ? 'block' : 'hidden' }}">

        <!-- Datos de la cita -->
        <div class="mb-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">1. Revisa tus datos</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <input
                        type="text"
                        id="nombreCliente"
                        wire:model="nombreCliente"
                        placeholder="Nombres"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 {{ $editandoDatos ? '' : 'bg-gray-100 cursor-not-allowed' }}"
                        required
                        {{ $editandoDatos ? '' : 'readonly disabled' }}
                    >
                </div>
                <div>
                    <input
                        type="text"
                        id="apellidoCliente"
                        wire:model="apellidoCliente"
                        placeholder="Apellidos"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 {{ $editandoDatos ? '' : 'bg-gray-100 cursor-not-allowed' }}"
                        required
                        {{ $editandoDatos ? '' : 'readonly disabled' }}
                    >
                </div>
                <div>
                    <input
                        type="email"
                        id="emailCliente"
                        wire:model="emailCliente"
                        placeholder="Email"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50 {{ $editandoDatos ? '' : 'bg-gray-100 cursor-not-allowed' }}"
                        required
                        {{ $editandoDatos ? '' : 'readonly disabled' }}
                    >
                </div>
                <div>
                    <input
    type="text"
    id="celularCliente"
    wire:model="celularCliente"
    placeholder="Celular (9 dígitos)"
    class="w-full rounded-md shadow-sm focus:ring focus:ring-opacity-50 {{ $editandoDatos ? '' : 'bg-gray-100 cursor-not-allowed' }} {{ strlen(preg_replace('/[^0-9]/', '', $celularCliente)) === 9 ? 'border-green-300 focus:border-green-500 focus:ring-green-500' : 'border-red-300 focus:border-red-500 focus:ring-red-500' }}"
    required
    maxlength="9"
    pattern="\d{9}"
    inputmode="numeric"
    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 9)"
    title="El número debe tener exactamente 9 dígitos"
    {{ $editandoDatos ? '' : 'readonly disabled' }}
>

                    <!-- Indicador visual del estado del celular -->
                    <div class="mt-1 flex items-center text-xs">
                        @if(strlen(preg_replace('/[^0-9]/', '', $celularCliente)) === 9)
                            <span class="text-green-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                                Número válido ({{ strlen(preg_replace('/[^0-9]/', '', $celularCliente)) }}/9 dígitos)
                            </span>
                        @else
                            <span class="text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                Faltan {{ 9 - strlen(preg_replace('/[^0-9]/', '', $celularCliente)) }} dígitos ({{ strlen(preg_replace('/[^0-9]/', '', $celularCliente)) }}/9)
                            </span>
                        @endif
                    </div>
                    
@error('celularCliente')
        <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
    @enderror

                </div>
            </div>
            <div class="flex items-center gap-4">
                @if($editandoDatos)
                    <button 
                        type="button" 
                        wire:click="guardarDatosCliente"
                        class="px-3 py-1 bg-primary-600 text-white text-sm rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        Guardar
                    </button>
                    <button 
                        type="button" 
                        wire:click="cancelarEdicionDatos"
                        class="px-3 py-1 bg-gray-300 text-white text-sm rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500"
                    >
                        Cancelar
                    </button>
                @else
                    <button 
                        type="button" 
                        wire:click="habilitarEdicionDatos"
                        class="px-3 py-1 bg-primary-600 text-white text-sm rounded-md hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        Editar
                    </button>
                @endif
            </div>
        </div>
        <br>

        <!-- Local -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-4">2. Elige el local</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4" wire:key="locales-container-{{ $localSeleccionado }}">
                @foreach ($locales as $key => $local)
                    <div class="flex items-center p-1 sm:p-3 border rounded-lg {{ $localSeleccionado == $key ? 'border-primary-500 bg-primary-50' : 'border-gray-300' }}" wire:key="local-{{ $key }}">
                        <div class="flex items-center h-5 mt-1 p-1 sm:p-2">
                            <input type="radio" id="local-{{ $key }}" name="local" value="{{ $key }}" wire:model.live="localSeleccionado" class="h-4 w-4 text-primary-600 border-gray-300 focus:ring-primary-500">
                        </div>
                        <div class="ml-3 text-sm">
                            <label for="local-{{ $key }}" class="font-medium text-gray-700">{{ $local['nombre'] }}</label>
                            <p class="text-gray-500 text-xs">{{ $local['direccion'] }}</p>
                            <p class="text-gray-500 text-xs">{{ $local['telefono'] }}</p>
                        </div>
                        <div class="ml-auto flex space-x-2 gap-4">
                            @if(!empty($local['maps_url']))
                                <!-- Botón Maps -->
                                <a href="{{ $local['maps_url'] }}" target="_blank" class="text-primary-600 hover:text-primary-800">
                                    <img src="/images/maps.svg" alt="Maps" class="w-6 h-6 sm:w-9 sm:h-9 border border-primary-500 rounded-md">
                                </a>
                            @endif
                            @if(!empty($local['waze_url']))
                                <!-- Botón Waze -->
                                <a href="{{ $local['waze_url'] }}" target="_blank" class="text-primary-600 hover:text-primary-800">
                                    <img src="/images/waze.svg" alt="Waze" class="w-6 h-6 sm:w-9 sm:h-9 border border-primary-500 rounded-md">
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <br>

        <!-- Fecha y hora -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-4">3. Elige una fecha y hora</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Calendario -->
                <div class="border rounded-lg p-4">
                    <!-- Navegación del mes y año -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <button type="button" class="flex items-center justify-center w-8 h-8 text-primary-600" wire:click="cambiarMes(-1)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <span class="text-lg font-medium text-primary-800">{{ $this->nombreMesActual }}</span>
                            <button type="button" class="flex items-center justify-center w-8 h-8 text-primary-600" wire:click="cambiarMes(1)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>

                        <div class="flex items-center space-x-2">
                            <button type="button" class="flex items-center justify-center w-8 h-8 text-primary-600" wire:click="cambiarAno(-1)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <span class="text-lg font-medium text-primary-800">{{ $anoActual }}</span>
                            <button type="button" class="flex items-center justify-center w-8 h-8 text-primary-600" wire:click="cambiarAno(1)">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Días de la semana -->
                    <div class="grid grid-cols-7 gap-1 text-center mb-2">
                        <div class="text-base font-medium text-primary-800">L</div>
                        <div class="text-base font-medium text-primary-800">M</div>
                        <div class="text-base font-medium text-primary-800">M</div>
                        <div class="text-base font-medium text-primary-800">J</div>
                        <div class="text-base font-medium text-primary-800">V</div>
                        <div class="text-base font-medium text-primary-800">S</div>
                        <div class="text-base font-medium text-primary-800">D</div>
                    </div>

                    <!-- Días del mes -->
                    <div class="grid grid-cols-7 gap-1 text-center">
                        @foreach($diasCalendario as $dia)
                            <div
                                class="py-2 text-base {{ $dia['disponible'] ? 'cursor-pointer hover:bg-primary-100 text-primary-600' : 'text-gray-400' }}
                                {{ $dia['fecha'] === $fechaSeleccionada ? 'bg-primary-500 text-white rounded-md' : '' }}
                                {{ $dia['esPasado'] || $dia['esHoy'] ? 'opacity-50' : '' }}"
                                @if($dia['disponible'])
                                    wire:click="seleccionarFecha('{{ $dia['fecha'] }}')"
                                @endif
                            >
                                {{ $dia['dia'] }}

                                @if($dia['esHoy'])
                                    <div class="w-1 h-1 {{ $dia['fecha'] === $fechaSeleccionada ? 'bg-white' : 'bg-primary-600' }} rounded-full mx-auto mt-1"></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Horarios disponibles con Progressive Loading -->
                <div class="border rounded-lg p-4">
                    <h3 class="text-lg font-medium text-primary-800 mb-4">Horarios disponibles</h3>

                    @if(empty($fechaSeleccionada))
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <p>Selecciona una fecha para ver los horarios disponibles</p>
                        </div>
                    @elseif(empty($horariosDisponibles))
                        <div class="text-center py-8 text-gray-500">
                            <svg class="w-8 h-8 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p>No hay horarios disponibles para esta fecha</p>
                            <p class="text-xs mt-2">Esta fecha puede estar bloqueada o todos los horarios están ocupados</p>
                        </div>
                    @else
                        {{-- ✅ HORARIOS CON PROGRESSIVE LOADING DIRECTO --}}
                        <style>
                            .horarios-grid {
                                display: grid;
                                grid-template-columns: repeat(3, 1fr);
                                gap: 8px;
                            }

                            .horario-item {
                                cursor: pointer;
                            }

                            .horario-card {
                                width: 100%;
                                height: 48px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                text-align: center;
                                border-radius: 0.5rem;
                                border-width: 1px;
                                font-size: 0.75rem;
                                padding: 0.25rem;
                            }

                            .slot-checking {
                                opacity: 0.7;
                                pointer-events: none;
                                position: relative;
                            }

                            .slot-checking::after {
                                content: '';
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                width: 16px;
                                height: 16px;
                                margin: -8px 0 0 -8px;
                                border: 2px solid #e5e7eb;
                                border-top: 2px solid #3b82f6;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                            }

                            @keyframes spin {
                                0% { transform: rotate(0deg); }
                                100% { transform: rotate(360deg); }
                            }

                            .slot-available {
                                /* Disponible */
                            }

                            .slot-unavailable {
                                opacity: 0.5;
                                pointer-events: none;
                            }
                        </style>

                        {{-- Indicador de validación global con barra de progreso --}}
                        <div id="validation-indicator" style="display: none;" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm text-blue-700 font-medium">Validando disponibilidad en tiempo real...</span>
                                <span id="progress-text" class="text-xs text-blue-600 font-bold">0%</span>
                            </div>
                            <div class="w-full bg-blue-200 rounded-full h-3 overflow-hidden">
                                <div id="progress-bar" class="h-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full transition-all duration-500 ease-out" style="width: 0%;"></div>
                            </div>
                        </div>

                        {{-- 🔍 DEBUG: Información de capacidad y citas --}}
                        @if(!empty($horariosDisponibles))
                        <div id="debug-capacity-info" class="mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-xs" style="display: none;">
                            <div class="font-semibold text-yellow-800 mb-2">🔍 DEBUG - Información de Capacidad:</div>
                            <div class="grid grid-cols-2 md:grid-cols-8 gap-2 text-yellow-700">
                                <div><strong>Centro:</strong> {{ $localSeleccionado ?? 'N/A' }}</div>
                                <div><strong>Fecha:</strong> {{ $fechaSeleccionada ?? 'N/A' }}</div>
                                <div><strong>Total:</strong> {{ $debugInfo['total_slots'] ?? count($horariosDisponibles) }}</div>
                                <div><strong>Validados:</strong> {{ $debugInfo['slots_validados'] ?? 'N/A' }}</div>
                                <div><strong>Disponibles:</strong> {{ $debugInfo['slots_disponibles'] ?? 'N/A' }}</div>
                                <div><strong>HCP (X):</strong> {{ $debugInfo['hcp'] ?? 'N/A' }}</div>
                                <div><strong>No-HCP:</strong> {{ $debugInfo['no_hcp'] ?? 'N/A' }}</div>
                                <div><strong>Total citas:</strong> {{ $debugInfo['total_citas'] ?? 'N/A' }}</div>
                            </div>
                            <div class="mt-1 text-xs text-yellow-600">
                                <strong>Método:</strong> {{ $debugInfo['validation_method'] ?? 'N/A' }}
                            </div>
                            <div class="mt-2 text-xs text-yellow-600">
                                <div class="font-medium">Estado:
                                    <span id="validation-status" class="
                                        @if(str_contains($debugInfo['status'] ?? '', 'Listo'))
                                            text-green-600 font-semibold
                                        @elseif(str_contains($debugInfo['status'] ?? '', 'progreso'))
                                            text-blue-600 font-semibold
                                        @elseif(str_contains($debugInfo['status'] ?? '', 'Completada'))
                                            text-green-600 font-semibold
                                        @else
                                            text-orange-600 font-semibold
                                        @endif
                                    ">{{ $debugInfo['status'] ?? 'Pendiente' }}</span>
                                </div>
                                <div id="capacity-breakdown" class="mt-1 max-h-64 overflow-y-auto font-mono text-xs">
                                    @if(isset($debugInfo['details']) && str_contains($debugInfo['details'], '✅'))
                                        <pre class="whitespace-pre-wrap">{{ $debugInfo['details'] }}</pre>
                                    @else
                                        {{ $debugInfo['details'] ?? 'Selecciona una fecha para ver detalles...' }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="horarios-grid">
                            @foreach($horariosDisponibles as $horario)
                                @php
                                    // SMART: Detectar si es estructura nueva o string legacy
                                    $time = is_array($horario) ? $horario['time'] : $horario;
                                    $isAvailable = is_array($horario) ? ($horario['is_available'] ?? true) : true;
                                    $capacityInfo = is_array($horario) ? ($horario['capacity_info'] ?? []) : [];
                                    
                                    // Definir clases CSS según disponibilidad
                                    if (!$isAvailable) {
                                        $cardClasses = 'horario-card horario-no-disponible bg-gray-100 text-gray-400 cursor-not-allowed';
                                        $clickAction = '';
                                    } else {
                                        $cardClasses = $horaSeleccionada === $time ? 
                                            'horario-card text-white bg-primary-600' : 
                                            'horario-card text-primary-600 hover:border-primary-600 cursor-pointer';
                                        $clickAction = "wire:click=\"seleccionarHora('$time')\"";
                                    }
                                @endphp
                                
                                <div class="horario-item">
                                    <div
                                        class="{{ $cardClasses }}"
                                        {!! $clickAction !!}
                                        data-time="{{ $time }}"
                                        @if(!$isAvailable) title="No disponible - {{ $capacityInfo['existing_appointments'] ?? 0 }}/{{ $capacityInfo['max_capacity'] ?? 0 }} ocupado" @endif
                                    >
                                        <span>{{ $time }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        @if(count($horariosDisponibles) < 3)
                            <div class="mt-4 text-xs text-gray-500 bg-yellow-50 p-2 rounded-md border border-yellow-200">
                                <p class="flex items-center">
                                    <svg class="w-4 h-4 mr-1 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    Quedan pocos horarios disponibles para esta fecha
                                </p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        <br>

        <!-- Servicio -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-4">4. Elige el servicio</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="border rounded-lg p-4 {{ in_array('Mantenimiento periódico', $serviciosSeleccionados) ? 'border-primary-500 bg-primary-50' : 'border-gray-300' }}">
                    <div class="flex items-start mb-4">
                        <div class="flex items-center h-6 mt-1">
                            <input
                                type="checkbox"
                                id="servicio-mantenimiento"
                                wire:click="toggleServicio('Mantenimiento periódico')"
                                {{ in_array('Mantenimiento periódico', $serviciosSeleccionados) ? 'checked' : '' }}
                                class="h-4 w-4 text-primary-600 border-gray-300 focus:ring-primary-500"
                            >
                        </div>
                        <div class="p-1">
                            <label for="servicio-mantenimiento" class="font-medium text-gray-700">Mantenimiento periódico</label>
                            <p class="text-gray-500 text-xs">Servicio según el kilometraje</p>
                        </div>
                    </div>

                    @if (in_array('Mantenimiento periódico', $serviciosSeleccionados))
                        <div class="mt-6 mb-4">
                            <label for="tipoMantenimiento" class="block text-sm font-medium text-gray-700 mb-2">Tipo de mantenimiento</label>
                            <select
                                id="tipoMantenimiento"
                                wire:model.live="tipoMantenimiento"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                            >
                                <option value="">Selecciona un mantenimiento</option>
                                @foreach($tiposMantenimientoDisponibles as $id => $nombre)
                                    <option value="{{ $nombre }}">{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-medium text-gray-700 mb-2">Modalidad</p>
                            <div class="space-y-2" wire:key="modalidades-{{ $localSeleccionado }}-{{ $tipoMantenimiento }}">
                                @foreach($modalidadesDisponibles as $value => $label)
                                    <div class="flex items-center">
                                        <input type="radio" id="modalidad-{{ $loop->index }}" name="modalidad" value="{{ $value }}" wire:model="modalidadServicio" class="h-4 w-4 text-primary-600 border-gray-300 focus:ring-primary-500">
                                        <label for="modalidad-{{ $loop->index }}" class="p-2 text-sm text-gray-700">
                                            @if($value === 'Mantenimiento Express')
                                                Mantenimiento Express
                                                @if($tipoExpress)
                                                    <br>
                                                    <span class="text-xs text-gray-500">({{ $tipoExpress }})</span>
                                                @endif
                                            @else
                                                {{ $label }}
                                            @endif
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mt-6 mb-4">
                            <label for="tipoMantenimiento" class="block text-sm font-medium text-gray-400 mb-2">Tipo de mantenimiento</label>
                            <select
                                id="tipoMantenimiento"
                                disabled
                                class="w-full rounded-md border-gray-300 shadow-sm bg-gray-100 text-gray-400 cursor-not-allowed"
                            >
                                <option value="">Selecciona un mantenimiento</option>
                            </select>
                        </div>

                        <div class="mt-4">
                            <p class="text-sm font-medium text-gray-400 mb-2">Modalidad</p>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="radio" disabled class="h-4 w-4 text-gray-400 border-gray-300 cursor-not-allowed">
                                    <label class="p-2 text-sm text-gray-400">Regular</label>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="border rounded-lg p-4 {{ in_array('Campañas / otros', $serviciosSeleccionados) ? 'border-primary-500 bg-primary-50' : 'border-gray-300' }}">
                    <!-- Primer servicio -->
                    <div class="flex items-start mb-4">
                        <div class="flex items-center mt-6">
                            <input
                                type="checkbox"
                                id="servicio-consultas-1"
                                wire:click="toggleServicio('Campañas / otros')"
                                {{ in_array('Campañas / otros', $serviciosSeleccionados) ? 'checked' : '' }}
                                class="h-4 w-4 text-primary-600 border-gray-300 focus:ring-primary-500"
                            >
                        </div>
                        <div class="p-3">
                            <label for="servicio-consultas-1" class="font-medium text-gray-700">Otros Servicios</label>
                            <p class="text-gray-500 text-xs">Servicio en base a pedido del cliente (Ej: lavado, lubriexpress)</p>
                        </div>
                    </div>

                    <!-- Dropdown de servicios adicionales -->
                    @if (in_array('Campañas / otros', $serviciosSeleccionados))
                        @if(count($serviciosAdicionalesDisponibles) > 0)
                            <div class="mb-6">
                                <label for="servicioAdicional" class="block text-sm font-medium text-gray-700 mb-2">Elige una opción</label>
                                <select
                                    id="servicioAdicional"
                                    wire:model.live="servicioAdicionalSeleccionado"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"
                                >
                                    <option value="">Selecciona otro servicio</option>
                                    @foreach($serviciosAdicionalesDisponibles as $id => $nombre)
                                        <option value="servicio_{{ $id }}">{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    @else
                        @if(count($serviciosAdicionalesDisponibles) > 0)
                            <div class="mb-6">
                                <label for="servicioAdicional" class="block text-sm font-medium text-gray-400 mb-2">Elige una opción</label>
                                <select
                                    id="servicioAdicional"
                                    disabled
                                    class="w-full rounded-md border-gray-300 shadow-sm bg-gray-100 text-gray-400 cursor-not-allowed"
                                >
                                    <option value="">Selecciona otro servicio</option>
                                </select>
                            </div>
                        @endif
                    @endif

                    <!-- Servicios adicionales seleccionados -->
                    @if(count($serviciosAdicionales) > 0)
                        <div class="mt-4 mb-6">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Servicios adicionales seleccionados:</h3>
                            <div class="space-y-2">
                                @foreach($serviciosAdicionales as $index => $servicio)
                                    @if(str_starts_with($servicio, 'servicio_'))
                                        <div class="flex items-center justify-between bg-blue-50 border border-blue-200 rounded-lg p-3">
                                            <span class="text-sm text-blue-800">
                                                {{ $opcionesServiciosAdicionales[$servicio] ?? $servicio }}
                                            </span>
                                            <button
                                                type="button"
                                                wire:click="eliminarServicioAdicional('{{ $servicio }}')"
                                                class="text-blue-600 hover:text-blue-800 text-sm"
                                            >
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <br>

        <!-- Servicios adicionales -->
        @if(count($campanasDisponibles) > 0)
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-4">5. Elige entre nuestras campañas del mes (opcional)</h2>

            <!-- Estilos para el carrusel -->
            <style>
                .carousel-container {
                    position: relative;
                    width: 100%;
                    overflow: hidden;
                }

                .carousel-items {
                    display: flex;
                    overflow-x: auto;
                    scroll-behavior: smooth;
                    -webkit-overflow-scrolling: touch;
                    scrollbar-width: none;
                }

                .carousel-items::-webkit-scrollbar {
                    display: none;
                }

                .carousel-item {
                    flex: 0 0 100%;
                    width: 100%;
                    padding: 0 8px;
                    box-sizing: border-box;
                }

                @media (min-width: 768px) {
                    .carousel-item {
                        flex: 0 0 33.333%;
                        width: 33.333%;
                    }
                }

                .carousel-nav {
                    position: absolute;
                    top: 50%;
                    transform: translateY(-50%);
                    width: 40px;
                    height: 40px;
                    background: white;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    cursor: pointer;
                    z-index: 10;
                    border: 1px solid #e5e7eb;
                }

                .carousel-nav-left {
                    left: 5px;
                }

                .carousel-nav-right {
                    right: 5px;
                }
            </style>

            <!-- Campañas disponibles -->
            <div class="mt-2" wire:key="campanas-container-{{ $localSeleccionado }}-{{ count($campanasDisponibles) }}">
                <div x-data="{
                    activeSlide: 0,
                    totalSlides: {{ count($campanasDisponibles) }},
                    slidesPerView: window.innerWidth < 768 ? 1 : 3,
                    campanaSeleccionada: @entangle('campanaSeleccionada'),
                    campanas: @js($campanasDisponibles),
                    getTituloSeleccionado() {
                        const campana = this.campanas.find(c => c.id == this.campanaSeleccionada);
                        return campana ? campana.titulo : 'Ninguna';
                    },
                    init() {
                        window.addEventListener('resize', () => {
                            this.slidesPerView = window.innerWidth < 768 ? 1 : 3;
                        });
                    },
                    next() {
                        if (this.activeSlide < this.totalSlides - this.slidesPerView) {
                            this.activeSlide++;
                            this.scrollToSlide();
                        }
                    },
                    prev() {
                        if (this.activeSlide > 0) {
                            this.activeSlide--;
                            this.scrollToSlide();
                        }
                    },
                    scrollToSlide() {
                        const container = this.$refs.carousel;
                        const slideWidth = container.offsetWidth / this.slidesPerView;
                        container.scrollTo({
                            left: this.activeSlide * slideWidth,
                            behavior: 'smooth'
                        });
                    }
                }" x-init="init()" class="carousel-container">
                    <div x-ref="carousel" class="carousel-items">
                        @foreach($campanasDisponibles as $index => $campana)
                            <div class="carousel-item" wire:key="campana-{{ $campana['id'] }}">
                                <div
                                    class="border rounded-lg overflow-hidden cursor-pointer"
                                    :class="campanaSeleccionada == '{{ $campana['id'] }}' ? 'border-primary-500 border-2' : 'border-gray-300'"
                                    @click="campanaSeleccionada = '{{ $campana['id'] }}'"
                                >
                                    <img src="{{ $campana['imagen'] }}" alt="{{ $campana['titulo'] }}" class="w-full h-96 object-cover" loading="lazy">
                                    <div class="p-2" :class="campanaSeleccionada == '{{ $campana['id'] }}' ? 'bg-primary-100' : ''">
                                        <h4 class="text-sm font-medium" :class="campanaSeleccionada == '{{ $campana['id'] }}' ? 'text-primary-800' : ''">{{ $campana['titulo'] }}</h4>
                                        <p class="text-xs text-gray-400 mt-1">
                                            @if(isset($campana['fecha_fin']))
                                                Válido hasta: {{ \Carbon\Carbon::parse($campana['fecha_fin'])->format('d/m/Y') }}
                                            @else
                                                Campaña permanente
                                            @endif
                                        </p>
                                    </div>                                        
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <!-- Botones de navegación -->
                    <div @click="prev()" x-show="activeSlide > 0" class="carousel-nav carousel-nav-left">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </div>
                    <div @click="next()" x-show="activeSlide < totalSlides - slidesPerView" class="carousel-nav carousel-nav-right">
                        <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                    <div class="mt-4 text-center">
                        <span class="font-semibold">Campaña seleccionada: </span>
                        <span class="text-primary-700" x-text="getTituloSeleccionado()"></span>
                    </div>
                </div>
            </div>
        </div>
        @endif
        <br>

        <!-- Comentarios -->
        <div class="mb-4">
            <h2 class="text-xl font-semibold mb-4">Comentario u observación</h2>
            <textarea 
                id="comentarios" 
                wire:model="comentarios" 
                rows="5" 
                placeholder="Ejemplo: Revisar el nivel de refrigerante" 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring focus:ring-primary-500 focus:ring-opacity-50"></textarea>
            <p class="text-xs text-gray-500 mt-1">Si la cita incluye diagnóstico o revisión, realizaremos la confirmación por teléfono</p>
        </div>

        <!-- Botón de finalizar paso 1 -->
        <div class="flex justify-center gap-4">
            <button type="button" wire:click="volverAVehiculos" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </div>
            </button>
            <button type="button" wire:click="finalizarAgendamiento" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                Continuar
            </button>
        </div>
        </div>

        <!-- Paso 2: Resumen de datos -->
        <div class="{{ $pasoActual == 2 ? 'block' : 'hidden' }}">
            <h2 class="text-xl font-semibold mb-4">Resumen</h2>
            <p class="text-gray-600 mb-4">Notificaremos el agendamiento de tu cita en el siguiente correo y celular. Si deseas cambiarlos vuelve al paso anterior.</p>

            <div class="bg-white rounded-lg mb-4">
                <!-- Datos personales -->
                <div class="mb-4">
                    <h3 class="font-medium text-primary-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos personales</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-800 mb-1">{{ $nombreCliente }} {{ $apellidoCliente }}</div>
                            <div class="text-gray-800 mb-1">{{ $celularCliente }}</div>
                            <div class="text-gray-800 mb-1">{{ $emailCliente }}</div>
                        </div>
                    </div>
                </div>

                <!-- Datos del vehículo -->
                <div class="mb-4">
                    <h3 class="font-medium text-success-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos del vehículo</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm font-bold text-success-800 mb-1">Modelo</div>
                            <div class="text-gray-800 mb-2">{{ $vehiculo['modelo'] ?: 'No especificado' }}</div>
                            <div class="text-sm font-bold text-success-800 mb-1">Placa</div>
                            <div class="text-gray-800">{{ $vehiculo['placa'] ?: 'No especificado' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Datos de la cita -->
                <div class="mb-4">
                    <h3 class="font-medium text-info-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos de la cita</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm font-bold text-info-800 mb-1">Local</div>
                            <div class="text-gray-800 mb-2">
                                @php
                                    $indiceLocal = (isset($localSeleccionado) && is_scalar($localSeleccionado) && $localSeleccionado !== '' && $localSeleccionado !== null) ? $localSeleccionado : null;
                                @endphp
                                @if($indiceLocal !== null && isset($locales[$indiceLocal]['nombre']))
                                    {{ $locales[$indiceLocal]['nombre'] }}
                                @else
                                    No seleccionado
                                @endif
                            </div>
                            <div class="text-sm font-bold text-primary-800 mb-1">Fecha y hora</div>
                            <div class="text-gray-800 mb-2">{{ $fechaSeleccionada ?: 'No seleccionada' }} - {{ $horaSeleccionada ?: 'No seleccionada' }}</div>
                            <div class="text-sm font-bold text-primary-800 mb-1">Servicios</div>
                            <div class="text-gray-800 mb-2">
                                @if(count($serviciosSeleccionados) > 0)
                                    {{ implode(', ', array_map(fn($s) => $s === 'Campañas / otros' ? 'Otros Servicios' : $s, $serviciosSeleccionados)) }}
                                @else
                                    No seleccionado
                                @endif
                            </div>
                            @if (in_array('Mantenimiento periódico', $serviciosSeleccionados))
                                <div class="bg-gray-50">
                                    <div class="text-sm font-bold text-primary-800 mb-1">Tipo de Mantenimiento</div>
                                    <div class="text-gray-800 mb-2">{{ $tipoMantenimiento ?: 'No seleccionado' }}</div>
                                </div>

                                <div class="bg-gray-50">
                                    <div class="text-sm font-bold text-primary-800 mb-1">Modalidad</div>
                                    <div class="text-gray-800 mb-2">{{ $modalidadServicio ?: 'No seleccionado' }}</div>
                                </div>
                            @endif

                            <div class="text-sm font-bold text-primary-800 mb-1">Otros Servicios</div>
                            <div class="text-gray-800 mb-2">
                                @php
                                    $campana = !empty($campanaSeleccionada)
                                        ? collect($campanasDisponibles)->firstWhere('id', $campanaSeleccionada)
                                        : null;
                                    
                                    $campanaTitulo = $campana['titulo'] ?? null;

                                    $adicionales = [];
                                    if ($campanaTitulo) {
                                        $adicionales[] = $campanaTitulo;
                                    }

                                    if (count($serviciosAdicionales) > 0) {
                                        foreach ($serviciosAdicionales as $servicio) {
                                            $adicionales[] = $opcionesServiciosAdicionales[$servicio] ?? $servicio;
                                        }
                                    }
                                @endphp

                                @if (count($adicionales) > 0)
                                    {{ implode(', ', $adicionales) }}
                                @else
                                    Ninguno
                                @endif
                            </div>

                            <div class="text-sm font-bold text-primary-800 mb-1">Comentario</div>
                            <div class="text-gray-800 mb-2">{{ $comentarios ?: 'Sin comentarios' }}</div>
                           
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botones de navegación paso 2 -->
            <div class="flex justify-center gap-4">
                <button type="button" wire:click="volver" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Volver
                    </div>
                </button>
                @php
                    $celularValido = strlen(preg_replace('/[^0-9]/', '', $celularCliente)) === 9;
                @endphp
                
                <div class="flex flex-col items-center">
                    <button 
                        type="button" 
                        wire:click="continuar" 
                        class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 {{ $celularValido ? 'bg-primary-600 hover:bg-primary-700 focus:ring-success-500' : 'bg-gray-400 cursor-not-allowed' }}"
                        wire:loading.attr="disabled"
                        {{ $celularValido ? '' : 'disabled' }}
                    >
                        <span wire:loading.remove wire:target="continuar">
                            @if($celularValido)
                                {{ $editMode ? 'Reprogramar Cita' : 'Agendar' }}
                            @else
                                Corrige el celular para continuar
                            @endif
                        </span>
                        <span wire:loading wire:target="continuar" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>
                    
                    @if(!$celularValido)
                        <p class="text-red-600 text-xs mt-2 text-center">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            Debes corregir el número de celular antes de continuar
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Paso 3: Confirmación -->
        <div class="{{ $pasoActual == 3 ? 'block' : 'hidden' }}">
            
            <h2 class="text-xl font-semibold mb-4">Resumen</h2>

            <div class="bg-white rounded-lg mb-4">
                <!-- Datos personales -->
                <div class="mb-4">
                    <h3 class="font-medium text-primary-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos personales</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-gray-800 mb-1">{{ $nombreCliente }} {{ $apellidoCliente }}</div>
                            <div class="text-gray-800 mb-1">{{ $celularCliente }}</div>
                            <div class="text-gray-800 mb-1">{{ $emailCliente }}</div>
                        </div>
                    </div>
                </div>

                <!-- Datos del vehículo -->
                <div class="mb-4">
                    <h3 class="font-medium text-success-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos del vehículo</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm font-bold text-success-800 mb-1">Modelo</div>
                            <div class="text-gray-800 mb-2">{{ $vehiculo['modelo'] ?: 'No especificado' }}</div>
                            <div class="text-sm font-bold text-success-800 mb-1">Placa</div>
                            <div class="text-gray-800">{{ $vehiculo['placa'] ?: 'No especificado' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Datos de la cita -->
                <div class="mb-4">
                    <h3 class="font-medium text-info-700 text-lg border-b border-gray-200 pb-2 mb-4">Datos de la cita</h3>

                    <div class="space-y-3">
                        <div class="bg-gray-50 p-3 rounded-lg">
                            <div class="text-sm font-bold text-info-800 mb-1">Local</div>
                            <div class="text-gray-800 mb-2">
                                @php
                                    $indiceLocal = (isset($localSeleccionado) && is_scalar($localSeleccionado) && $localSeleccionado !== '' && $localSeleccionado !== null) ? $localSeleccionado : null;
                                @endphp
                                @if($indiceLocal !== null && isset($locales[$indiceLocal]['nombre']))
                                    {{ $locales[$indiceLocal]['nombre'] }}
                                @else
                                    No seleccionado
                                @endif
                            </div>
                            <div class="text-sm font-bold text-primary-800 mb-1">Fecha y hora</div>
                            <div class="text-gray-800 mb-2">{{ $fechaSeleccionada ?: 'No seleccionada' }} - {{ $horaSeleccionada ?: 'No seleccionada' }}</div>
                            <div class="text-sm font-bold text-primary-800 mb-1">Servicios</div>
                            <div class="text-gray-800 mb-2">
                                @if(count($serviciosSeleccionados) > 0)
                                    {{ implode(', ', array_map(fn($s) => $s === 'Campañas / otros' ? 'Otros Servicios' : $s, $serviciosSeleccionados)) }}
                                @else
                                    No seleccionado
                                @endif
                            </div>
                            @if (in_array('Mantenimiento periódico', $serviciosSeleccionados))
                                <div class="bg-gray-50">
                                    <div class="text-sm font-bold text-primary-800 mb-1">Tipo de Mantenimiento</div>
                                    <div class="text-gray-800 mb-2">{{ $tipoMantenimiento ?: 'No seleccionado' }}</div>
                                </div>

                                <div class="bg-gray-50">
                                    <div class="text-sm font-bold text-primary-800 mb-1">Modalidad</div>
                                    <div class="text-gray-800 mb-2">{{ $modalidadServicio ?: 'No seleccionado' }}</div>
                                </div>
                            @endif

                            
                            <div class="text-sm font-bold text-primary-800 mb-1">Otros Servicios</div>
                            <div class="text-gray-800 mb-2">
                                @php
                                    $campana = !empty($campanaSeleccionada)
                                        ? collect($campanasDisponibles)->firstWhere('id', $campanaSeleccionada)
                                        : null;
                                    
                                    $campanaTitulo = $campana['titulo'] ?? null;

                                    $adicionales = [];
                                    if ($campanaTitulo) {
                                        $adicionales[] = $campanaTitulo;
                                    }

                                    if (count($serviciosAdicionales) > 0) {
                                        foreach ($serviciosAdicionales as $servicio) {
                                            $adicionales[] = $opcionesServiciosAdicionales[$servicio] ?? $servicio;
                                        }
                                    }
                                @endphp

                                @if (count($adicionales) > 0)
                                    {{ implode(', ', $adicionales) }}
                                @else
                                    Ninguno
                                @endif
                            </div>

                            <div class="text-sm font-bold text-primary-800 mb-1">Comentario</div>
                            <div class="text-gray-800 mb-2">{{ $comentarios ?: 'Sin comentarios' }}</div>
                           
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-center">
                <button type="button" wire:click="cerrarYVolverACitas" class="px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-danger-500">
                    Cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Pop-ups -->
    <div x-data="{ show: @entangle('mostrarModalPopups') }" x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <!-- Fondo oscuro -->
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50"></div>

        <!-- Modal centrado con tamaño fijo -->
        <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-4 scale-95" class="bg-white rounded-lg shadow-xl w-full max-w-lg h-auto transform transition-all flex flex-col relative">
    
            <!-- Contenido con altura fija -->
            <div class="px-6 pt-6 pb-4">
                <div class="text-center">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        ¿Deseas conocer nuestros servicios?
                    </h3>
                    <p class="text-sm text-gray-500 mb-2">
                        Recibe información sobre nuestros servicios. Elige uno y
                    </p>
                    <p class="text-sm text-gray-500 mb-6">
                        recibirás un mensaje por whatsapp.
                    </p>

                    <!-- Lista de pop-ups con altura para exactamente 3 cards -->
                    <div style="height: 360px;" class="overflow-y-auto space-y-4 pr-2">
                        @foreach($popupsDisponibles as $popup)
                            <div class="flex items-center border rounded-lg p-2 h-28 {{ in_array($popup['id'], $popupsSeleccionados) ? 'border-primary-500 bg-primary-50' : 'border-gray-300' }}">
                                <div class="flex items-center mr-4 h-5">
                                    <input
                                        type="checkbox"
                                        id="popup-{{ $popup['id'] }}"
                                        wire:click="togglePopup({{ $popup['id'] }})"
                                        {{ in_array($popup['id'], $popupsSeleccionados) ? 'checked' : '' }}
                                        class="h-4 w-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
                                    >
                                </div>
                                <div class="ml-4 text-sm flex-grow flex items-center">
                                    <label for="popup-{{ $popup['id'] }}" class="font-medium text-gray-700">{{ $popup['nombre'] }}</label>
                                </div>
                                <div class="flex-shrink-0">
                                    <img src="{{ $popup['imagen'] }}" alt="{{ $popup['nombre'] }}" class="h-16 w-16 object-cover rounded border border-gray-200">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Botones fijos en la parte inferior -->
            <div class="border-t border-gray-200 px-6 py-4 flex gap-3">
                <!-- Botón Cancelar -->
                <button wire:click="cancelarYVolverAVehiculos" type="button" class="flex-1 px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Omitir información
                </button>

                <!-- Botón Solicitar -->
                <button wire:click="solicitarInformacion" type="button" class="flex-1 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    Solicitar información
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Resumen de Pop-ups -->
    <div x-data="{ show: @entangle('mostrarModalResumenPopups') }" x-show="show" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Fondo oscuro -->
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
                <div class="absolute inset-0 bg-black/50"></div>
            </div>

            <!-- Modal -->
            <div x-show="show" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                
                <!-- Contenido del modal -->
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4 text-center">
                                Resumen
                            </h3>

                            <!-- Mensaje de éxito -->
                            <div class="bg-green-100 border-l-4 border-green-500 text-primary-600 mb-4 pt-4">
                                <div class="flex justify-center items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-lg text-green-500">Datos enviados con éxito</p>
                                    </div>
                                </div>
                            </div>

                            <p class="text-sm text-gray-700 mb-1 text-center">
                                Pronto serás contactado por whatsapp para recibir
                            </p>
                            <p class="text-sm text-gray-700 mb-4 text-center">
                                más información sobre los siguientes servicios.
                            </p>

                            <!-- Lista de pop-ups seleccionados -->
                            <div class="mt-4 space-y-4">
                                @foreach($popupsDisponibles as $popup)
                                    @if(in_array($popup['id'], $popupsSeleccionados))
                                        <div class="flex items-center border rounded-lg p-2 border-gray-300">
                                            <div class="ml-4 text-sm flex-grow">
                                                <span class="font-medium text-gray-700">{{ $popup['nombre'] }}</span>
                                            </div>
                                            <div class="ml-auto">
                                                <img src="{{ $popup['imagen'] }}" alt="{{ $popup['nombre'] }}" class="h-16 w-16 object-cover flex-shrink-0 rounded border border-gray-200">
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones del modal -->
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button wire:click="cerrarResumen" type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loader de progreso para C4C -->
    @if($citaStatus === 'processing')
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 px-4">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform transition-all duration-300 scale-100">
                <div class="text-center">
                    <!-- Spinner animado más elegante -->
                    <div class="mb-6 relative">
                        <div class="mx-auto h-16 w-16 relative">
                            <!-- Círculo exterior -->
                            <div class="absolute inset-0 rounded-full border-4 border-blue-100"></div>
                            <!-- Círculo animado -->
                            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-blue-500 animate-spin"></div>
                            <!-- Punto central -->
                            <div class="absolute inset-4 bg-blue-500 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                    
                    <!-- Título más atractivo -->
                    <h3 class="text-xl font-bold text-gray-900 mb-2">🚗 Agendando tu cita</h3>
                    
                    <!-- Barra de progreso moderna -->
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-4 shadow-inner">
                        <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-1000 ease-out shadow-sm" 
                             style="width: {{ $citaProgress }}%"></div>
                    </div>
                    
                    <!-- Info de progreso -->
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-sm font-medium text-blue-600">{{ $citaProgress }}% completado</span>
                        <span class="text-xs text-gray-500">⏱️ Tiempo estimado: 30s</span>
                    </div>
                    
                    <!-- Advertencia elegante -->
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p class="text-xs text-amber-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            Por favor no cierres esta ventana
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de error -->
    @if($citaStatus === 'failed')
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 px-4 animate-fadeIn">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform transition-all duration-300 scale-100 animate-slideUp">
                <div class="text-center">
                    <!-- Icono de error mejorado -->
                    <div class="mb-6 relative">
                        <div class="mx-auto w-20 h-20 bg-red-100 rounded-full flex items-center justify-center animate-bounce">
                            <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center shadow-lg">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Título más impactante -->
                    <h3 class="text-2xl font-bold text-gray-900 mb-3">❌ Oops! Algo salió mal</h3>
                    
                    <!-- Mensaje de error en card -->
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-r-lg">
                        <p class="text-sm text-red-800 leading-relaxed">{{ $citaMessage }}</p>
                    </div>
                    
                    <!-- Sugerencia amigable -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                        <p class="text-xs text-blue-700 flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            💡 Intenta seleccionar otra fecha u horario disponible
                        </p>
                    </div>
                    
                    <!-- Botones con estilos inline garantizados -->
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 24px;">
                        <button wire:click="volverAVehiculos" 
                                style="display: flex; align-items: center; justify-content: center; padding: 12px 16px; background-color: #2563EB; color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background-color 0.2s ease;"
                                onmouseover="this.style.backgroundColor='#1D4ED8'"
                                onmouseout="this.style.backgroundColor='#2563EB'">
                            <svg style="width: 16px; height: 16px; margin-right: 8px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            🏠 Volver al inicio
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de reintentando -->
    @if($citaStatus === 'retrying')
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 px-4 animate-fadeIn">
            <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full transform transition-all duration-300 scale-100">
                <div class="text-center">
                    <!-- Icono de reintentando mejorado -->
                    <div class="mb-6 relative">
                        <div class="mx-auto w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center">
                            <div class="w-16 h-16 bg-yellow-500 rounded-full flex items-center justify-center shadow-lg animate-bounce">
                                <svg class="w-8 h-8 text-white animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Título más dinámico -->
                    <h3 class="text-xl font-bold text-gray-900 mb-3">🔄 Reintentando conexión</h3>
                    
                    <!-- Mensaje en card -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
                        <p class="text-sm text-yellow-800 leading-relaxed">{{ $citaMessage }}</p>
                    </div>
                    
                    <!-- Barra de progreso animada más atractiva -->
                    <div class="w-full bg-gray-200 rounded-full h-3 mb-6 shadow-inner overflow-hidden">
                        <div class="bg-gradient-to-r from-yellow-400 via-yellow-500 to-yellow-600 h-3 rounded-full animate-pulse shadow-sm">
                            <div class="h-full bg-gradient-to-r from-transparent via-white to-transparent opacity-30 animate-shimmer"></div>
                        </div>
                    </div>
                    
                    <!-- Info con iconos -->
                    <div class="space-y-3">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p class="text-xs text-blue-700 flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd"></path>
                                </svg>
                                Detectamos un problema temporal
                            </p>
                        </div>
                        
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                            <p class="text-xs text-green-700 flex items-center justify-center">
                                <svg class="w-4 h-4 mr-2 animate-spin" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path>
                                </svg>
                                ⚡ Reintentando automáticamente...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de éxito -->
    @if($citaStatus === 'completed')
        <div class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 animate-fadeIn">
            <!-- Modal centrado con altura máxima y scroll interno -->
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] transform transition-all duration-300 scale-100 animate-slideUp flex flex-col">
                <!-- Contenido con scroll interno -->
                <div class="overflow-y-auto flex-1 p-8">
                    <div class="text-center">
                        <!-- Icono de éxito elegante -->
                        <div class="mb-6 relative">
                            <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center animate-bounce-gentle">
                                <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center shadow-lg glow-blue">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            </div>
                            <!-- Confetti effect -->
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-2xl animate-bounce">🎉</div>
                            </div>
                        </div>

                        <!-- Título celebratorio -->
                        <h3 class="text-2xl font-bold text-gray-900 mb-3">🎉 ¡Cita generada exitosamente!</h3>

                        <!-- Información de la cita -->
                        <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6 rounded-r-lg">
                            <p class="text-sm text-green-800 leading-relaxed font-medium">
                                Tu cita ha sido registrada en nuestro sistema
                            </p>
                        </div>

                        <!-- Próximos pasos -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h4 class="text-sm font-semibold text-blue-900 mb-2">📱 Próximos pasos:</h4>
                            <ul class="text-xs text-blue-700 space-y-1 text-left">
                                <li class="flex items-center">
                                    <svg class="w-3 h-3 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd"></path>
                                </svg>
                                Recibirás una confirmación por WhatsApp
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-3 h-3 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    Te recordaremos 1 día antes de tu cita
                                </li>
                                <li class="flex items-center">
                                    <svg class="w-3 h-3 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.707-10.293a1 1 0 00-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L9.414 11H13a1 1 0 100-2H9.414l1.293-1.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    Lleva tu vehiculo en la fecha programada y con 15 min de anticipacion
                                </li>
                                <li class="flex mt-2 items-center font-semibold text-center">
                                De haber elegido una revision o diagnostico, nos contactaremos contigo
                                </li>
                            </ul>
                                               </div>
                    </div>
                </div>

                <!-- Botones fijos en la parte inferior -->
                <div class="border-t border-gray-200 p-6 flex gap-3">
                    <!-- Botón Continuar -->
                    <button wire:click="continuarDespuesDeExito"
                            class="flex-1 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        ✅ Continuar
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- JavaScript para polling -->
    <script>
        let pollingInterval = null;
        
        // Escuchar el evento para iniciar polling
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('start-polling', (data) => {
                console.log('🔄 Iniciando polling para job:', data.jobId);
                
                // Limpiar polling anterior si existe
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                }
                
                // Iniciar polling cada 2 segundos
                pollingInterval = setInterval(() => {
                    console.log('📡 Verificando status del job...');
                    @this.call('checkJobStatus');
                }, 2000);
            });
            
            // Escuchar el evento para detener polling
            Livewire.on('stop-polling', () => {
                console.log('⏹️ Deteniendo polling');
                if (pollingInterval) {
                    clearInterval(pollingInterval);
                    pollingInterval = null;
                }
            });
        });
        
        // Limpiar polling al salir de la página
        window.addEventListener('beforeunload', () => {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
        });
        
        // Escuchar evento de cita agendada exitosamente para localStorage
        window.addEventListener('citaAgendadaExitosamente', function(event) {
            console.log('✅ Evento citaAgendadaExitosamente recibido:', event.detail);
            localStorage.setItem('cita_agendada_recientemente', JSON.stringify(event.detail));
        });
    </script>
</x-filament-panels::page>

{{-- ✅ SOLUCIÓN KISS: JavaScript directo en la página --}}
<script>
console.log('🚀 Progressive Loader KISS iniciando');

// Actualizar debug info inmediatamente
setTimeout(function() {
    const validationStatus = document.getElementById('validation-status');
    const capacityBreakdown = document.getElementById('capacity-breakdown');

    console.log('🔍 Actualizando debug info inicial');

    if (validationStatus) {
        validationStatus.textContent = 'Horarios cargados - Sin validar';
        validationStatus.className = 'text-orange-600 font-semibold';
        console.log('✅ Status actualizado');
    }

    if (capacityBreakdown) {
        capacityBreakdown.innerHTML = '<div class="text-orange-600">Horarios cargados desde C4C/Local. Selecciona una fecha para validar capacidad con zTope.</div>';
        console.log('✅ Breakdown actualizado');
    }
}, 1000);

// Escuchar eventos de Livewire para actualizar debug
document.addEventListener('livewire:updated', function() {
    console.log('🔄 Livewire updated - Verificando horarios');
    const timeButtons = document.querySelectorAll('[data-time]');
    const validationStatus = document.getElementById('validation-status');

    if (timeButtons.length > 0 && validationStatus) {
        console.log('📊 Horarios detectados:', timeButtons.length);
        validationStatus.textContent = 'Horarios detectados (' + timeButtons.length + ') - Listo para validar';
        validationStatus.className = 'text-green-600 font-semibold';
    }
});

// Escuchar evento de Livewire cuando se cargan horarios
document.addEventListener('horarios-cargados-activar-progressive', function() {
    console.log('🔄 Evento recibido: activando validación');

    // Actualizar debug info cuando se recibe el evento (oculto por decisión de UX)
    const validationStatus = document.getElementById('validation-status');
    const capacityBreakdown = document.getElementById('capacity-breakdown');

    if (validationStatus) {
        validationStatus.textContent = 'Evento recibido - Iniciando validación';
        validationStatus.className = 'text-blue-600 font-semibold';
    }

    setTimeout(function() {
        const timeButtons = document.querySelectorAll('[data-time]');
        if (timeButtons.length > 0) {
            console.log('🚀 Activando validación para', timeButtons.length, 'botones');

            // Mostrar indicador global y animar barra de progreso
            const indicator = document.getElementById('validation-indicator');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const validationStatus = document.getElementById('validation-status');
            const capacityBreakdown = document.getElementById('capacity-breakdown');

            if (indicator) {
                indicator.style.display = 'block';
            }

            // Actualizar estado de debug
            if (validationStatus) {
                validationStatus.textContent = 'En progreso... 🔄';
                validationStatus.className = 'text-blue-600 font-semibold';
            }

            if (capacityBreakdown) {
                capacityBreakdown.innerHTML = '<div class="text-blue-600">Validando capacidad en tiempo real...</div>';
            }

            if (progressBar && progressText) {
                // Función para actualizar progreso
                function updateProgress(percentage) {
                    progressBar.style.width = percentage + '%';
                    progressText.textContent = percentage + '%';
                }

                // Iniciar en 0%
                updateProgress(0);

                // Animar la barra de progreso paso a paso
                setTimeout(() => updateProgress(25), 200);
                setTimeout(() => updateProgress(50), 600);
                setTimeout(() => updateProgress(75), 1200);
                setTimeout(() => updateProgress(90), 1800);
            }

            // Aplicar efectos de loading a cada botón
            timeButtons.forEach(function(button) {
                button.classList.add('slot-checking');
            });

            // Llamar validación
            if (window.Livewire) {
                const wireElement = document.querySelector('[wire\\:id]');
                if (wireElement) {
                    const component = window.Livewire.find(wireElement.getAttribute('wire:id'));
                    if (component) {
                        component.call('validarCapacidadProgresiva');
                    }
                }
            }
        }
    }, 200);
});

// Escuchar resultado de validación
document.addEventListener('progressive-validation-completed', function(event) {
    const data = event.detail[0];

    // Completar barra de progreso y ocultar indicador
    const indicator = document.getElementById('validation-indicator');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    if (progressBar && progressText) {
        progressBar.style.width = '100%';
        progressText.textContent = '100%';
    }

    setTimeout(() => {
        if (indicator) {
            indicator.style.display = 'none';
        }
        if (progressBar && progressText) {
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
        }
    }, 500);

    if (data.success && data.slots) {
        console.log('✅ Validación completada');

        // Actualizar información de debug
        const debugInfo = document.getElementById('debug-capacity-info');
        const validationStatus = document.getElementById('validation-status');
        const capacityBreakdown = document.getElementById('capacity-breakdown');

        // Mantener panel de debug oculto (sin cambiar display)

        if (validationStatus) {
            validationStatus.textContent = 'Completada ✅';
            validationStatus.className = 'text-green-600 font-semibold';
        }

        if (capacityBreakdown) {
            let breakdown = '';
            data.slots.forEach(function(slot) {
                const capacity = slot.capacity_validation || {};
                const status = slot.is_available ? '✅' : '❌';
                const maxCap = capacity.max_capacity || 'N/A';
                const existing = capacity.existing_appointments || 'N/A';
                const remaining = capacity.remaining_capacity || 'N/A';

                const hcp = capacity.hcp_count ?? 'N/A';
                const noHcp = capacity.no_hcp_count ?? 'N/A';
                const hcpFrom = Array.isArray(capacity.hcp_from) ? capacity.hcp_from : [];
                const plates = Array.isArray(capacity.plates) ? capacity.plates : [];
                const platesText = plates.length ? ` | Placas: ${plates.join(', ')}` : '';

                breakdown += `<div class=\"flex justify-between items-center py-1 border-b border-yellow-200\">\
                    <span>${status} ${slot.start_time_formatted}</span>\
                    <span class=\"text-xs\">zTope: ${maxCap} | Citas: ${existing} | Libre: ${remaining} | <span class='px-1 rounded bg-purple-100 text-purple-700'>HCP: ${hcp} (-5h)</span> | No-HCP: ${noHcp}${platesText}</span>\
                </div>`;
            });
            capacityBreakdown.innerHTML = breakdown;
        }

        // Actualizar botones de horarios
        data.slots.forEach(function(slot) {
            const timeValue = slot.start_time_formatted;
            const timeButton = document.querySelector('[data-time="' + timeValue + '"]');

            if (timeButton) {
                timeButton.classList.remove('slot-checking');

                if (slot.is_available) {
                    timeButton.classList.add('slot-available');
                    timeButton.disabled = false;
                } else {
                    timeButton.classList.add('slot-unavailable');
                    timeButton.disabled = true;
                }
            }
        });
    } else {
        console.error('❌ Error en validación:', data.error);
        // Fallback: hacer todos disponibles
        document.querySelectorAll('.slot-checking').forEach(function(button) {
            button.classList.remove('slot-checking');
            button.disabled = false;
        });
    }
});
</script>

@push('styles')
<style>
    /* Animaciones personalizadas para los modales */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(20px) scale(0.95); 
        }
        to { 
            opacity: 1;
            transform: translateY(0) scale(1); 
        }
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    
    @keyframes pulse-gentle {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    @keyframes bounce-gentle {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }
    
    .animate-fadeIn {
        animation: fadeIn 0.3s ease-out;
    }
    
    .animate-slideUp {
        animation: slideUp 0.3s ease-out;
    }
    
    .animate-shimmer {
        animation: shimmer 2s infinite linear;
    }
    
    .animate-pulse-gentle {
        animation: pulse-gentle 2s infinite;
    }
    
    .animate-bounce-gentle {
        animation: bounce-gentle 1s infinite;
    }
    
    /* Efectos de glassmorphism */
    .backdrop-blur-sm {
        backdrop-filter: blur(4px);
    }
    
    /* Hover effects mejorados */
    .hover-lift:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    }
    
    /* Gradientes personalizados */
    .bg-gradient-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .bg-gradient-error {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .bg-gradient-warning {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    /* Efectos de resplandor */
    .glow-blue {
        box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
    }
    
    .glow-red {
        box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
    }
    
    .glow-yellow {
        box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener('livewire:load', function () {
        Livewire.on('mostrarResumen', function () {
            document.getElementById('resumen-cita').scrollIntoView({ behavior: 'smooth' });
        });

        // Evento para actualizar la vista cuando cambian los horarios
        Livewire.on('horarios-actualizados', function () {
            console.log('Horarios actualizados, refrescando vista');
            // Forzar actualización de Alpine.js
            document.querySelectorAll('[x-data]').forEach(el => {
                if (el.__x) {
                    el.__x.updateElements(el);
                }
            });
        });

        // Evento para mostrar notificaciones
        Livewire.on('notify', function (data) {
            console.log('Notificación:', data);

            // Crear elemento de notificación
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-500 transform translate-x-full';

            // Aplicar estilos según el tipo
            if (data.type === 'error') {
                notification.className += ' bg-red-500 text-white';
            } else if (data.type === 'success') {
                notification.className += ' bg-green-500 text-white';
            } else {
                notification.className += ' bg-primary-500 text-white';
            }

            // Agregar mensaje
            notification.textContent = data.message;

            // Agregar al DOM
            document.body.appendChild(notification);

            // Mostrar con animación
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
            }, 10);

            // Ocultar después de 3 segundos
            setTimeout(() => {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 3000);
        });
    });
</script>
@endpush
