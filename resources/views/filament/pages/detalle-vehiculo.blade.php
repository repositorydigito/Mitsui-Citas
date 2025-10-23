<div>
    <x-filament-panels::page>
        <div class="rounded-lg w-full">
            <h1 class="font-bold text-2xl mb-2">Detalle del vehículo</h1>
            <!-- Encabezado con título y botón de agendar cita -->
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-2 mb-2">
                <button type="button"
                    wire:click="agendarCita"
                    @disabled(!empty($citasAgendadas) && count($citasAgendadas) > 0)
                    class="px-4 py-2 rounded-md w-auto focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 {{ !empty($citasAgendadas) && count($citasAgendadas) > 0 ? 'bg-gray-400 text-white cursor-not-allowed' : 'bg-primary-500 text-white hover:bg-primary-700' }}">
                    Agendar cita
                </button>
            </div>

            <!-- Sección de información general -->
            <div class="mb-4" x-data="{ expanded: true }">
                <div class="rounded-md mb-4 bg-primary-600 text-white p-3 flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
                    <h2 class="font-medium">Información general</h2>
                    <svg class="w-5 h-5 transform transition-transform duration-200"
                         :class="{ 'rotate-180': expanded }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>

                <div x-show="expanded"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                     class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Columna 1: Información básica -->
                    <div class="bg-white rounded-lg shadow-sm p-4 space-y-4">
                        <div class="flex justify-between">
                            <div class="w-32 font-bold text-blue-900">Modelo</div>
                            <div class="text-gray-800">{{ $vehiculo['modelo'] ?? 'No disponible' }}</div>
                        </div>

                        <div class="flex justify-between">
                            <div class="w-32 font-bold text-blue-900">Kilometraje</div>
                            <div class="text-gray-800">{{ $vehiculo['kilometraje'] ?? 'No disponible' }}</div>
                        </div>

                        <div class="flex justify-between">
                            <div class="w-32 font-bold text-blue-900">Placa</div>
                            <div class="text-gray-800">{{ $vehiculo['placa'] ?? 'No disponible' }}</div>
                        </div>
                    </div>

                    <!-- Columna 2: Mantenimientos programados -->
                    <div class="bg-white rounded-lg shadow-sm p-4">
                        <div class="flex flex-row justify-between items-start">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="font-bold text-blue-900 uppercase">MANTENIMIENTOS PREPAGADOS</h3>                        
                            </div>

                            <!-- Icono con modal (desktop y mobile) -->
                            <div x-data="{ showModal: false }" class="relative">
                                <button
                                    type="button"
                                    @click="showModal = true"
                                    class="text-blue-700 hover:text-blue-900 focus:outline-none transition-colors duration-200 p-1 rounded-full hover:bg-blue-100"
                                    title="Información importante"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z" />
                                    </svg>
                                </button>

                                <!-- Modal -->
                                <div
                                    x-show="showModal"
                                    x-transition:enter="transition ease-out duration-300"
                                    x-transition:enter-start="opacity-0"
                                    x-transition:enter-end="opacity-100"
                                    x-transition:leave="transition ease-in duration-200"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="fixed inset-0 z-50 overflow-y-auto"
                                    @click.away="showModal = false"
                                    style="display: none;"
                                >
                                    <!-- Fondo oscuro -->
                                    <div x-show="showModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity">
                                        <div class="absolute inset-0 bg-black/50"></div>
                                    </div>
                                    <!-- Modal Content -->
                                    <div class="flex items-center justify-center min-h-screen px-4 py-6">
                                        <div 
                                            class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-auto"
                                            @click.stop
                                            x-transition:enter="transition ease-out duration-300"
                                            x-transition:enter-start="opacity-0 transform scale-95"
                                            x-transition:enter-end="opacity-100 transform scale-100"
                                            x-transition:leave="transition ease-in duration-200"
                                            x-transition:leave-start="opacity-100 transform scale-100"
                                            x-transition:leave-end="opacity-0 transform scale-95"
                                        >
                                            <!-- Header -->
                                            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                                                <h3 class="text-lg font-semibold text-blue-900 flex items-center">
                                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 20a8 8 0 100-16 8 8 0 000 16z" />
                                                    </svg>
                                                    Información importante
                                                </h3>
                                                <button
                                                    type="button"
                                                    @click="showModal = false"
                                                    class="text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600 transition-colors duration-200"
                                                >
                                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                            
                                            <!-- Body -->
                                            <div class="p-6">
                                                <div class="flex items-start">
                                                    <div class="flex-shrink-0">
                                                        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <p class="text-sm text-gray-700 leading-relaxed">
                                                            Recuerda realizar el mantenimiento antes de la fecha de vencimiento indicada.
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Footer -->
                                            <div class="px-6 py-4 bg-gray-50 rounded-b-lg">
                                                <button
                                                    type="button"
                                                    @click="showModal = false"
                                                    class="w-full px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200"
                                                >
                                                    Entendido
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div> 
                        </div>
                        

                        <div class="space-y-4">
                            <!-- <div class="flex justify-between">
                                <div class="w-32 font-bold text-blue-900">Vencimiento</div>
                                <div class="text-gray-800">{{ $mantenimiento['vencimiento'] }}</div>
                            </div> -->

                            <div class="flex justify-between items-start">
                                <div class="w-32 font-bold text-blue-900">Disponibles</div>
                                <div class="text-gray-800">
                                    @foreach ($mantenimiento['disponibles'] as $disponible)
                                        <div>{{ $disponible }}</div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    
                </div>
            </div>

            <!-- Sección de citas agendadas -->
            <div class="mb-4" x-data="{ expanded: true }">
                <div class="rounded-md mb-4 bg-primary-600 text-white p-3 flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
                    <h2 class="font-medium">Cita agendada</h2>
                    <svg class="w-5 h-5 transform transition-transform duration-200"
                         :class="{ 'rotate-180': expanded }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>

                <!-- Diseño responsive para citas agendadas -->
                <div x-show="expanded"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                     class="space-y-4">
                    @foreach ($citasAgendadas as $cita)
                        <div class="bg-white border border-gray-200 rounded-lg p-4">
                            
                            <!-- Layout responsive: columna en móvil, fila en desktop -->
                            <div class="flex flex-col">

                                <!-- Sección Estado (Dinámico) -->
                                <div class="mb-4 md:mb-0 md:w-2/5">
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-xs font-medium text-gray-800 mb-4">Estado del servicio</div>
                                        <div class="flex flex-row justify-between gap-2">
                                            @php
                                                $etapas = $cita['estado_info']['etapas'] ?? [];
                                            @endphp

                                            <!-- Estado: Cita confirmada -->
                                            @php
                                                $citaCompletada = $etapas['cita_confirmada']['completado'] ?? false;
                                            @endphp
                                            <div style="display: flex; flex-direction: column; align-items: center;">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: {{ $citaCompletada ? '#059669' : '#d1d5db' }};">
                                                    @if($citaCompletada)
                                                        <svg style="width: 20px; height: 20px; color: white;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                    @else
                                                        <div style="width: 12px; height: 12px; border-radius: 50%; background-color: #9ca3af;"></div>
                                                    @endif
                                                </div>
                                                <span style="font-size: 12px; font-weight: 500; margin-top: 4px; text-align: center; color: {{ $citaCompletada ? '#059669' : '#6b7280' }};">
                                                    Cita Generada
                                                </span>
                                            </div>

                                            <!-- Estado: En trabajo -->
                                            @php
                                                $trabajoCompletado = $etapas['en_trabajo']['completado'] ?? false;
                                                $trabajoActivo = $etapas['en_trabajo']['activo'] ?? false;
                                            @endphp
                                            <div style="display: flex; flex-direction: column; align-items: center; opacity: {{ $trabajoCompletado ? '1' : '1' }};">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: {{ $trabajoActivo ? '#059669' : ($trabajoCompletado ? '#059669' : '#d1d5db') }};">
                                                
                                                        <svg style="width: 20px; height: 20px; color: white;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                   
                                                </div>
                                                <span style="font-size: 12px; font-weight: 500; margin-top: 4px; text-align: center; color: {{ $trabajoActivo ? '#059669' : ($trabajoCompletado ? '#059669' : '#6b7280') }};">
                                                    En trabajo
                                                </span>
                                            </div>

                                            <!-- Estado: Trabajo concluido -->
                                            @php
                                                $concluidoCompletado = $etapas['trabajo_concluido']['completado'] ?? false;
                                                $concluidoActivo = $etapas['trabajo_concluido']['activo'] ?? false;
                                            @endphp
                                            <div style="display: flex; flex-direction: column; align-items: center; opacity: {{ $concluidoCompletado ? '1' : '0.5' }};">
                                                <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: {{ $concluidoActivo ? '#059669' : ($concluidoCompletado ? '#059669' : '#d1d5db') }};">
                                                  
                                                        <svg style="width: 20px; height: 20px; color: white;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                        </svg>
                                                
                                                </div>
                                                <span style="font-size: 12px; font-weight: 500; margin-top: 4px; text-align: center; color: {{ $concluidoActivo ? '#059669' : ($concluidoCompletado ? '#059669' : '#6b7280') }};">
                                                    Trabajo concluido
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sección Datos (Completamente Dinámico) -->
                                <div class="mb-4 md:mb-0 md:w-1/3">
                                    <div class="bg-blue-50 p-3 rounded-lg">
                                        <div class="space-y-2">
                                            
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs font-medium text-blue-800">Fecha de la cita:</span>
                                                <span class="text-xs text-gray-600 mt-1">{{ $cita['fecha_cita'] }}</span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-xs font-medium text-blue-800">Hora de la cita:</span>
                                                <span class="text-xs text-gray-600 mt-1">{{ $cita['hora_cita'] ?? '' }}</span>
                                            </div>

                                            @if(!empty($cita['maintenance_type']))
                                                {{-- Cuando HAY mantenimiento: usar diseño original en la misma fila --}}
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-blue-800">Servicio:</span>
                                                    <span class="text-xs text-gray-600 mt-1">Mantenimiento {{ $cita['maintenance_type'] }}</span>
                                                </div>
                                            @elseif(!empty($cita['wildcard_selections']))
                                                {{-- Cuando NO hay mantenimiento pero SÍ hay servicios: mostrar en fila separada --}}
                                                <div class="space-y-1">
                                                    <div class="text-xs font-medium text-blue-800">Servicio:</div>
                                                    <div class="text-xs text-gray-600">
                                                        @php
                                                            $serviciosTexto = [];
                                                            if (!empty($cita['wildcard_selections']['servicios_adicionales'])) {
                                                                $serviciosTexto[] = implode(', ', $cita['wildcard_selections']['servicios_adicionales']);
                                                            }
                                                            if (!empty($cita['wildcard_selections']['campanas'])) {
                                                                $serviciosTexto[] = implode(', ', $cita['wildcard_selections']['campanas']);
                                                            }
                                                        @endphp
                                                        {{ !empty($serviciosTexto) ? implode(' | ', $serviciosTexto) : 'Servicios seleccionados' }}
                                                    </div>
                                                </div>
                                            @else
                                                {{-- Fallback: usar diseño original --}}
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-blue-800">Servicio:</span>
                                                    <span class="text-xs text-gray-600 mt-1">Mantenimiento {{ $cita['servicio'] ?? 'Servicio programado' }}</span>
                                                </div>
                                            @endif

                                            {{-- Mostrar sede en la misma fila cuando hay mantenimiento, en fila separada cuando no hay mantenimiento --}}
                                            @if(!empty($cita['maintenance_type']))
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-blue-800">Sede:</span>
                                                    <span class="text-xs text-gray-600">{{ $cita['sede'] ?? '-' }}</span>
                                                </div>
                                            @else
                                                <div class="space-y-1">
                                                    <div class="text-xs font-medium text-blue-800">Sede:</div>
                                                    <div class="text-xs text-gray-600">{{ $cita['sede'] ?? '-' }}</div>
                                                </div>
                                            @endif

                                            @if(($trabajoActivo || $trabajoCompletado) || ($concluidoActivo || $concluidoCompletado))
                                            <br>

                                            <div class="flex justify-between">
                                                <span class="text-xs font-medium text-blue-800">Fecha de entrega:</span>
                                                <span class="text-xs text-gray-600">{{ $cita['probable_entrega'] ?? '-' }}</span>
                                            </div>

                                            <div class="flex justify-between">
                                                <span class="text-xs font-medium text-blue-800">Asesor:</span>
                                                <span class="text-xs text-gray-600">{{ $cita['asesor'] ?? 'Por asignar' }}</span>
                                            </div>

                                            <div class="flex justify-between">
                                                <span class="text-xs font-medium text-blue-800">WhatsApp:</span>
                                                <span class="text-xs text-primary-600 font-medium">
                                                    @if(!empty($cita['whatsapp']) && $cita['whatsapp'] !== '-')
                                                        <a href="https://wa.me/{{ str_replace(['+', ' ', '-'], '', $cita['whatsapp']) }}"
                                                           target="_blank" class="text-green-600 hover:text-green-800">
                                                            {{ $cita['whatsapp'] }}
                                                        </a>
                                                    @else
                                                        -
                                                    @endif
                                                </span>
                                            </div>

                                            <div class="flex justify-between">
                                                <span class="text-xs font-medium text-blue-800">Correo:</span>
                                                <span class="text-xs text-primary-600 font-medium underline decoration-primary-500">
                                                    @if(!empty($cita['correo']))
                                                        <a href="mailto:{{ $cita['correo'] }}" class="text-blue-600 hover:text-blue-800">
                                                            {{ $cita['correo'] }}
                                                        </a>
                                                    @else
                                                        -
                                                    @endif
                                                </span>
                                            </div>
                                            @endif

                                            @if(($concluidoActivo || $concluidoCompletado) && !empty($cita['rut_pdf']))
                                                <br>                                                
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-green-800">Fecha de factura:</span>
                                                    <span class="text-xs text-gray-600">{{ $cita['fecha_factura'] ?? '-' }}</span>
                                                </div>

                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs font-medium text-green-800">Hora de factura:</span>
                                                    <span class="text-xs text-gray-600">{{ $cita['hora_factura'] ?? '-' }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <!-- Botones de acción -->
                                <div class="mt-4 pt-3 border-t border-gray-200">
                                    <div class="flex flex-row md:justify-end gap-2 md:gap-4">
                                        @if(($concluidoActivo || $concluidoCompletado) && !empty($cita['rut_pdf']))
                                            <a href="{{ $cita['rut_pdf'] }}" target="_blank" class="text-gray-600 hover:text-primary-800 flex items-center justify-center md:justify-start text-sm py-2 rounded-md hover:bg-primary-50">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                Ver comprobante
                                            </a>
                                        @endif
                                        @php
                                            // Modificación: Solo habilitar botones cuando la cita está CONFIRMADA
                                            // y NO está en ningún otro estado posterior (En trabajo, Trabajo concluido)
                                            $puedeEditar = ($etapas['cita_confirmada']['completado'] ?? false) && 
                                                          !($etapas['en_trabajo']['completado'] ?? false) && 
                                                          !($etapas['en_trabajo']['activo'] ?? false) && 
                                                          !($etapas['trabajo_concluido']['completado'] ?? false) && 
                                                          !($etapas['trabajo_concluido']['activo'] ?? false);
                                        @endphp

                                        @if($puedeEditar)
                                            <button type="button" wire:click="editarCita({{ json_encode($cita, JSON_HEX_APOS | JSON_HEX_QUOT) }})" onclick="console.log('🔧 Botón Editar clickeado:', @json($cita))" class="text-primary-600 hover:text-primary-800 flex items-center justify-center md:justify-start text-sm py-2 rounded-md hover:bg-primary-50">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Editar
                                            </button>
                                        @else
                                            <button type="button" 
                                                    disabled
                                                    class="text-gray-400 flex items-center justify-center md:justify-start text-sm py-2 px-3 rounded-md cursor-not-allowed opacity-50"
                                                    title="Solo se puede editar cuando la cita está confirmada y no ha iniciado el trabajo">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                                Editar
                                            </button>
                                        @endif
                                        @php
                                            // Modificación: Solo habilitar botones cuando la cita está CONFIRMADA
                                            // y NO está en ningún otro estado posterior (En trabajo, Trabajo concluido)
                                            $puedeAnular = ($etapas['cita_confirmada']['completado'] ?? false) && 
                                                          !($etapas['en_trabajo']['completado'] ?? false) && 
                                                          !($etapas['en_trabajo']['activo'] ?? false) && 
                                                          !($etapas['trabajo_concluido']['completado'] ?? false) && 
                                                          !($etapas['trabajo_concluido']['activo'] ?? false);
                                        @endphp

                                        @if($puedeAnular)
                                            <button type="button" 
                                                    wire:click="anularCita({{ json_encode($cita, JSON_HEX_APOS | JSON_HEX_QUOT) }})"
                                                    class="text-red-600 hover:text-red-800 flex items-center justify-center md:justify-start text-sm py-2 rounded-md hover:bg-red-50 transition-colors">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                                Anular
                                            </button>
                                        @else
                                            <button type="button" 
                                                    disabled
                                                    class="text-gray-400 flex items-center justify-center md:justify-start text-sm py-2 px-3 rounded-md cursor-not-allowed opacity-50"
                                                    title="Solo se puede anular cuando la cita está confirmada y no ha iniciado el trabajo">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                                                </svg>
                                                Anular
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Sección de historial de servicios -->
            <div x-data="{ expanded: true }">
                <div class="mb-4 rounded-md bg-primary-600 text-white p-3 flex justify-between items-center cursor-pointer" @click="expanded = !expanded">
                    <h2 class="font-medium">Historial de servicios</h2>
                    <svg class="w-5 h-5 transform transition-transform duration-200"
                         :class="{ 'rotate-180': expanded }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>

                <div x-show="expanded"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                     class="rounded-md overflow-x-auto">
                    <!-- Tabla visible solo en desktop -->
                    <div class="hidden md:block">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-primary-600">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                                        Servicio
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                                        Fecha
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                                        Sede
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                                        Asesor
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">
                                        Tipo de pago
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($this->historialPaginado as $servicio)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ $servicio['servicio'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ $servicio['fecha'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ $servicio['sede'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ $servicio['asesor'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ $servicio['tipo_pago'] }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Diseño vertical para móvil con acordeón -->
                    <div class="md:hidden space-y-4">
                        @foreach ($this->historialPaginado as $index => $servicio)
                            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden" x-data="{ expanded: false }">
                                <!-- Header del acordeón -->
                                <div class="p-4 cursor-pointer hover:bg-gray-50" @click="expanded = !expanded">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="text-gray-800 font-semibold">{{ $servicio['servicio'] }}</div>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-500 transform transition-transform duration-200"
                                             :class="{ 'rotate-180': expanded }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Contenido expandible -->
                                <div x-show="expanded"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 transform -translate-y-2"
                                     x-transition:enter-end="opacity-100 transform translate-y-0"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 transform translate-y-0"
                                     x-transition:leave-end="opacity-0 transform -translate-y-2"
                                     style="display: none;">
                                     <div class="px-4 pb-4 space-y-3 border-t border-gray-100">
                                        <div class="bg-gray-50 p-3 rounded-lg">
                                            <div class="flex flex-row justify-between">
                                                <div class="text-sm font-medium text-primary-800 mb-1">Fecha</div>
                                                <div class="text-sm text-gray-800">{{ $servicio['fecha'] }}</div>
                                            </div>
                                            <div class="flex flex-row justify-between">
                                                <div class="text-sm font-medium text-primary-800 mb-1">Sede</div>
                                                <div class="text-sm text-gray-800">{{ $servicio['sede'] }}</div>
                                            </div>
                                            <div class="flex flex-row justify-between">
                                                <div class="text-sm font-medium text-primary-800 mb-1">Asesor</div>
                                                <div class="text-sm text-gray-800">{{ $servicio['asesor'] }}</div>
                                            </div>
                                            <div class="flex flex-row justify-between">
                                                <div class="text-sm font-medium text-primary-800 mb-1">Tipo de pago</div>
                                                <div class="text-sm text-gray-800">{{ $servicio['tipo_pago'] }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Botón para volver -->
            <div class="mt-6">
                <button type="button" wire:click="volver" class="px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Volver a vehículos
                    </div>
                </button>
            </div>
        </div>
    </x-filament-panels::page>
</div>

<!-- SweetAlert2 CDN para mejores modales de confirmación -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- JavaScript para manejo de confirmación de anulación -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Escuchar el evento de mostrar confirmación de eliminación
    window.addEventListener('show-delete-confirmation', function(event) {
        // --- INICIO DE CÓDIGO DEFENSIVO ---
        if (!event.detail || event.detail.length < 2) {
            console.error('Error: No se recibieron datos suficientes en el evento show-delete-confirmation.', event);
            alert('Error: No se pudieron cargar los datos de la cita para la anulación.');
            return; // Detener ejecución si no hay datos
        }

        const uuid = event.detail[0]; // El UUID es el primer parámetro
        const citaData = event.detail[1]; // Los datos de la cita son el segundo parámetro
        console.log('UUID recibido:', uuid);
        console.log('Datos de cita recibidos:', citaData);

        if (!citaData) {
            console.error('Error: El objeto citaData no está definido en los datos del evento.', citaData);
            alert('Error: Datos de la cita incompletos.');
            return; // Detener ejecución si citaData no existe
        }
        // --- FIN DE CÓDIGO DEFENSIVO ---

        const fechaCita = citaData.fecha_cita || 'N/A';
        const horaCita = citaData.hora_cita || 'N/A';

        // Mostrar confirmación usando SweetAlert2 si está disponible, sino usar confirm nativo
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: '¿Anular esta cita?',
                html: `
                    <div class="text-left space-y-2">
                        <p><strong>Fecha:</strong> ${fechaCita}</p>
                        <p><strong>Hora:</strong> ${horaCita}</p>
                        <br>
                        <p class="text-red-600">⚠️ Esta acción no se puede deshacer</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, anular cita',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Llamar al método Livewire para confirmar la anulación
                    @this.call('confirmarAnulacion', uuid, citaData);
                }
            });
        } else {
            // Fallback para navegadores sin SweetAlert2
            const mensaje = `¿Está seguro de que desea anular esta cita?\n\n` +
                          `Fecha: ${fechaCita}\n` +
                          `Hora: ${horaCita}\n\n` +
                          `⚠️ Esta acción no se puede deshacer`;

            if (confirm(mensaje)) {
                @this.call('confirmarAnulacion', uuid, citaData);
            }
        }
    });

    // Escuchar evento para mostrar notificaciones
    window.addEventListener('show-notification', function(event) {
        const data = event.detail[0];
        showNotification(data.title, data.message, data.type);
    });
});

// Función para mostrar notificaciones
function showNotification(title, message, type = 'info') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: type,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } else {
        alert(title + ': ' + message);
    }
}
</script>