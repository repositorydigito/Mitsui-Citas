<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Botón para volver a la página anterior -->
        <div class="mb-6">
            <button 
                wire:click="volverPaginaAnterior"
                type="button"
                class="inline-flex items-center gap-2 px-2 py-2 text-sm font-medium text-primary-600 bg-white border border-primary-300 rounded-lg shadow-sm hover:bg-primary-50 hover:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-200 ease-in-out">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Atrás
            </button>
        </div>
        @if (!$modoEdicion)
            <!-- Vista de información personal -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-lg font-medium">Información personal</h2>
                </div>

                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Datos personales</h3>

                    <div class="overflow-hidden mb-4">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @php $userData = $this->getUserData(); @endphp
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Nombres</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">{{ $userData['nombres'] }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Apellidos</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">{{ $userData['apellidos'] }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Celular</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                                </svg>
                                                {{ $userData['celular'] ?: 'No registrado' }}
                                            </span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Correo</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center">
                                                <svg class="w-4 h-4 mr-1 text-primary-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                                </svg>
                                                {{ $userData['correo'] ?: 'No registrado' }}
                                            </span>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Documento</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">{{ $userData['tipo_documento'] ?: 'No registrado' }}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-3 w-40">
                                        <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Número de documento</p>
                                    </td>
                                    <td class="py-3 px-6">
                                        <p class="text-base text-gray-700 dark:text-gray-300">{{ $userData['numero_documento'] ?: 'No registrado' }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4">
                    <button
                        wire:click="iniciarEdicion"
                        type="button"
                        class="inline-flex items-center px-4 py-2 text-sm font-medium text-primary-600 bg-white border border-primary-600 rounded-lg hover:bg-primary-50 focus:z-10 focus:ring-2 focus:ring-primary-300"
                    >
                        <svg class="w-4 h-4 mr-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                        </svg>
                        Editar
                    </button>
                </div>
            </div>
        @else
            <!-- Modo de edición con pasos -->
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-lg font-medium">Editar datos personales</h2>
                </div>

                <!-- Indicador de pasos -->
                <div class="px-6 py-8 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex justify-center">
                        <div class="relative flex items-center w-full max-w-md">
                            <!-- Paso 1 -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="rounded-full transition duration-500 ease-in-out h-10 w-10 flex items-center justify-center {{ $pasoActual == 1 ? 'bg-primary-600 text-white' : 'bg-gray-300' }} z-10">
                                    <span class="text-center text-sm">1</span>
                                </div>
                                <div class="text-center mt-2 text-xs font-medium text-gray-500">Datos personales</div>
                            </div>

                            <!-- Línea de conexión 1-2 -->
                            <div class="flex-auto border-t-2 transition duration-500 ease-in-out {{ $pasoActual >= 2 ? 'border-primary-600' : 'border-gray-300' }} -mx-2 absolute left-0 right-0 top-5 z-0" style="width: 33%;left: 33%;"></div>

                            <!-- Paso 2 -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="rounded-full transition duration-500 ease-in-out h-10 w-10 flex items-center justify-center {{ $pasoActual == 2 ? 'bg-primary-600 text-white' : 'bg-gray-300' }} z-10">
                                    <span class="text-center text-sm">2</span>
                                </div>
                                <div class="text-center mt-2 text-xs font-medium text-gray-500">Resumen</div>
                            </div>

                            <!-- Línea de conexión 2-3 -->
                            <div class="flex-auto border-t-2 transition duration-500 ease-in-out {{ $pasoActual >= 3 ? 'border-primary-600' : 'border-gray-300' }} -mx-2 absolute right-0 top-5 z-0" style="width: 33%;"></div>

                            <!-- Paso 3 -->
                            <div class="flex flex-col items-center flex-1">
                                <div class="rounded-full transition duration-500 ease-in-out h-10 w-10 flex items-center justify-center {{ $pasoActual == 3 ? 'bg-primary-600 text-white' : 'bg-gray-300' }} z-10">
                                    <span class="text-center text-sm">3</span>
                                </div>
                                <div class="text-center mt-2 text-xs font-medium text-gray-500">Confirmación</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contenido según el paso actual -->
                <div class="p-6">
                    @if ($pasoActual == 1)
                        <!-- Paso 1: Formulario de datos personales -->
                        <h3 class="text-lg font-medium text-primary-600 dark:text-white mb-4">Datos personales</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <input
                                    type="text"
                                    id="nombres"
                                    placeholder="Nombres"
                                    wire:model="datosEdicion.nombres"
                                    class="w-full px-3 py-2 border border-primary-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                >
                                @error('datosEdicion.nombres') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <input
                                    type="text"
                                    id="apellidos"
                                    placeholder="Apellidos"
                                    wire:model="datosEdicion.apellidos"
                                    class="w-full px-3 py-2 border border-primary-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                >
                                @error('datosEdicion.apellidos') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <input
                                    type="email"
                                    id="correo"
                                    placeholder="Correo electrónico"
                                    wire:model="datosEdicion.correo"
                                    class="w-full px-3 py-2 border border-primary-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                    pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|pe)$"
                                    title="Ingrese un correo válido con terminación .com o .pe"
                                >
                                @error('datosEdicion.correo') <span class="text-primary-500 text-xs">Formato inválido</span> @enderror
                            </div>

                            <div>
                                <input
                                    type="text"
                                    id="celular"
                                    placeholder="Celular"
                                    wire:model.live="datosEdicion.celular"
                                    class="w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 {{ $this->mostrarErrorCelular ? 'border-red-500' : ($this->esCelularValido ? 'border-green-500' : 'border-primary-600') }}"
                                    maxlength="11"
                                    inputmode="numeric"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
                                >
                                @if($this->mostrarErrorCelular)
                                    <span class="text-red-500 text-xs">El número debe tener 9 dígitos (empezando con 9) u 11 dígitos (empezando con 5)</span>
                                @endif
                                @error('datosEdicion.celular') <span class="text-primary-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <select
                                    id="tipo_documento"
                                    wire:model="datosEdicion.tipo_documento"
                                    class="w-full px-3 py-2 border border-primary-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                >
                                    @foreach ($tiposDocumento as $key => $value)
                                        <option value="{{ $key }}">{{ $value }}</option>
                                    @endforeach
                                </select>
                                @error('datosEdicion.tipo_documento') <span class="text-red-500 text-xs">Formato inválido</span> @enderror
                            </div>

                            <div>
                                <input
                                    type="text"
                                    id="numero_documento"
                                    placeholder="Número de documento (máximo 12 caracteres)"
                                    wire:model="datosEdicion.numero_documento"
                                    class="w-full px-3 py-2 border border-primary-600 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                                    maxlength="12"
                                    oninput="this.value = this.value.replace(/[^A-Za-z0-9]/g, '').slice(0, 12).toUpperCase()"
                                >
                                @error('datosEdicion.numero_documento') <span class="text-red-500 text-xs">Formato invalido</span> @enderror
                            </div>
                        </div>
                    @elseif ($pasoActual == 2)
                        <!-- Paso 2: Resumen de los datos -->
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Resumen</h3>

                        <div class="overflow-hidden mb-6">
                            <table class="min-w-full">
                                <tbody>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Nombres</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">{{ $datosEdicion['nombres'] }}</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Apellidos</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">{{ $datosEdicion['apellidos'] }}</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Correo</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">
                                                <span class="inline-flex items-center">
                                                    <svg class="w-4 h-4 mr-1 text-primary-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                                    </svg>
                                                    {{ $datosEdicion['correo'] }}
                                                </span>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Celular</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">
                                                <span class="inline-flex items-center">
                                                    <svg class="w-4 h-4 mr-1 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z" />
                                                    </svg>
                                                    {{ $datosEdicion['celular'] }}
                                                </span>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Documento</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">{{ $datosEdicion['tipo_documento'] }}</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2">
                                            <p class="text-sm font-medium text-primary-800 dark:text-primary-400">Número de documento</p>
                                        </td>
                                        <td class="py-2 px-4">
                                            <p class="text-base text-gray-700 dark:text-gray-300">{{ $datosEdicion['numero_documento'] }}</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @elseif ($pasoActual == 3)
                        <!-- Paso 3: Confirmación -->
                        <div class="text-center py-8">
                            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-6">
                                <svg class="w-8 h-8 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <h3 class="text-xl font-medium text-gray-900 dark:text-white mb-2">¡Datos actualizados correctamente!</h3>
                            <p class="text-gray-600 dark:text-gray-400 mb-6">Tus datos personales han sido actualizados con éxito.</p>
                        </div>
                    @endif
                </div>

                <!-- Botones de navegación -->
                <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-center gap-6">
                    @if ($pasoActual == 1)
                        <button
                            wire:click="cancelarEdicion"
                            type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-primary-600 rounded-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-gray-300"
                        >
                            Cancelar
                        </button>

                        <button
                            wire:click="siguientePaso"
                            type="button"
                            @if(!$this->esCelularValido) disabled @endif
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:z-10 focus:ring-2 focus:ring-primary-300 {{ !$this->esCelularValido ? 'opacity-50 cursor-not-allowed' : '' }}"
                        >
                            Continuar
                        </button>
                    @elseif ($pasoActual == 2)
                        <button
                            wire:click="pasoAnterior"
                            type="button"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-primary-600 rounded-lg hover:bg-gray-50 focus:z-10 focus:ring-2 focus:ring-gray-300"
                        >
                            Volver
                        </button>

                        <button
                            wire:click="guardarCambios"
                            type="button"
                            @if(!$this->esCelularValido) disabled @endif
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:z-10 focus:ring-2 focus:ring-primary-300 {{ !$this->esCelularValido ? 'opacity-50 cursor-not-allowed' : '' }}"
                        >
                            Confirmar
                        </button>
                    @elseif ($pasoActual == 3)
                        <div class="mx-auto">
                            <button
                                wire:click="cerrarYRedirigir"
                                type="button"
                                class="inline-flex items-center px-6 py-3 text-sm font-medium text-white bg-primary-600 border border-transparent rounded-lg hover:bg-primary-700 focus:z-10 focus:ring-2 focus:ring-primary-300"
                            >
                                Cerrar
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
