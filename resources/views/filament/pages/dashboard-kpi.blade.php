<x-filament-panels::page>
    {{-- Filtros superiores --}}
    <div class="mb-2 rounded-lg">
        <div class="flex flex-wrap items-end gap-4">
            {{-- Filtro de marca --}}
            <div class="w-auto">
                <label for="marca" class="block text-sm font-medium text-primary-600 mb-2">Marca</label>
                <div class="relative">
                    <select
                        id="marca"
                        wire:model.live="marcaSeleccionada"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        style="min-width: 160px;"
                    >
                        @foreach ($marcas as $marca)
                            <option value="{{ $marca }}">{{ $marca }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    </div>
                </div>
            </div>

            {{-- Filtro de local --}}
            <div class="w-auto">
                <label for="local" class="block text-sm font-medium text-primary-600 mb-2">Local</label>
                <div class="relative">
                    <select
                        id="local"
                        wire:model.live="localSeleccionado"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        style="min-width: 160px;"
                    >
                        @foreach ($locales as $codigo => $nombre)
                            <option value="{{ $codigo }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Filtro de fecha --}}
            <div class="w-auto">
                <label for="fecha" class="block text-sm font-medium text-primary-600 mb-2">Fecha</label>
                <div class="relative">
                    <input
                        type="text"
                        id="fecha"
                        wire:model.live="rangoFechas"
                        placeholder="Seleccionar rango"
                        class="w-auto border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500 datepicker"
                        style="min-width: 220px;"
                        autocomplete="off"
                        readonly
                    >
                </div>
            </div>
        </div>
    </div>

    {{-- Mensaje cuando no hay datos --}}
    @if($citasGeneradas === 0)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">
                        No hay datos en el rango seleccionado
                    </h3>
                </div>
            </div>
        </div>
    @endif

    <div class="grid md:grid-cols-2 lg:grid-cols-2 gap-6">
    <!-- Columna izquierda: KPIs en 2 filas -->
    <div class="space-y-6">
        <!-- Primera fila de KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">CITAS GENERADAS</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $citasGeneradas }}</p>
            </div>
            <!-- ✅ MODIFICADO: Mostrar citas en trabajo en lugar de citas efectivas -->
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">CITAS EFECTIVAS</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $citasEnTrabajo }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">% EFECTIVIDAD</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $porcentajeEfectividad }}%</p>
            </div>            
        </div>

        <!-- Segunda fila de KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-xs font-semibold text-gray-700 mb-2">CITAS DIFERIDAS / REPROGRAMADAS</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $citasDiferidas }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">CITAS CANCELADAS</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $citasCanceladas }}</p>
            </div>
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">CITAS NO SHOW</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $citasNoShow }}</p>
            </div>
        </div>

        <!-- Cantidad de usuarios -->
         <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-lg shadow border border-gray-200 text-center">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">CANTIDAD DE USUARIOS</h3>
                <p class="font-bold text-primary-600" style="font-size: 2rem !important;">{{ $cantidadUsuarios }}</p>
            </div>
        </div>
    </div>

    <!-- Columna derecha: Gráficos lado a lado -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-semibold text-gray-700">CITAS POR MANTENIMIENTO</h3>
                <span class="text-sm text-gray-500">({{ $citasMantenimiento }})</span>
            </div>
            <br>
            <div class="h-32 flex items-center justify-center" wire:ignore 
                 x-data="{ porcentaje: {{ $porcentajeMantenimiento }} }"
                 x-init="$watch('porcentaje', value => updateMantenimientoChart(value))">
                <canvas id="gaugeMantenimiento" class="max-h-full"></canvas>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-sm font-semibold text-gray-700">CITAS SIN MANTENIMIENTO</h3>
                <span class="text-sm text-gray-500">({{ $citasMantenimientoPrepagados }})</span>
            </div>
            <br>
            <div class="h-32 flex items-center justify-center" wire:ignore
                 x-data="{ porcentaje: {{ $porcentajePrepagados }} }"
                 x-init="$watch('porcentaje', value => updatePrepagadosChart(value))">
                <canvas id="gaugePrepagados" class="max-h-full"></canvas>
            </div>
        </div>
    </div>
</div>

    {{-- Filtros para gráficos inferiores --}}
    <div class="mb-6 bg-blue-50 rounded-lg">
        <div class="flex flex-wrap items-end gap-4">
            {{-- Filtro de marca --}}
            <div class="w-auto">
                <label for="marca2" class="block text-sm font-medium text-primary-600 mb-2">Marca</label>
                <div class="relative">
                    <select
                        id="marca2"
                        wire:model.live="marcaSeleccionadaGraficos"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        style="min-width: 160px;"
                    >
                        @foreach ($marcas as $marca)
                            <option value="{{ $marca }}">{{ $marca }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    </div>
                </div>
            </div>
            
            {{-- Filtro de local --}}
            <div class="w-auto">
                <label for="local2" class="block text-sm font-medium text-primary-600 mb-2">Local</label>
                <div class="relative">
                    <select
                        id="local2"
                        wire:model.live="localSeleccionadoGraficos"
                        class="w-full border-gray-300 rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                        style="min-width: 160px;"
                    >
                        @foreach ($localesGraficos as $codigo => $nombre)
                            <option value="{{ $codigo }}">{{ $nombre }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Cuarta fila: Gráficos de línea y barras --}}
    <div class="grid grid-cols-1 mb-6">
        {{-- Gráfico: Cantidad de Citas --}}
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">CANTIDAD DE CITAS</h3>
            <div class="h-96 py-4" wire:ignore>
                <canvas id="chartCantidadCitas"></canvas>
            </div>
        </div>
    </div>

    {{-- Scripts para el datepicker --}}
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>

    {{-- Script para Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        let chartMantenimiento = null;
        let chartPrepagados = null;
        let chartCantidadCitas = null;
        let chartTiempoPromedio = null;
        let flatpickrInstance = null;

        function getEfectividadData(data) {
            if (!data || !data.porcentajesEfectividad) {
                return [];
            }

            return data.porcentajesEfectividad;
        }

        document.addEventListener('DOMContentLoaded', function () {
            initDatepicker();
            initCharts();
        });

        document.addEventListener('livewire:navigated', function () {
            initDatepicker();
            initCharts();
        });

        document.addEventListener('livewire:update', function () {
            setTimeout(() => {
                initDatepicker();
                updateCharts();
            }, 100);
        });

        window.addEventListener('updateCharts', function (event) {
            setTimeout(() => {
                updateChartsWithData(event.detail);
            }, 50);
        });

        if (typeof Livewire !== 'undefined') {
            Livewire.hook('message.processed', (message, component) => {
                setTimeout(() => {
                    initDatepicker();
                    updateCharts();
                }, 100);
            });
        }

        document.addEventListener('livewire:updated', function (event) {
            if (event.detail && event.detail.name === 'rangoFechas') {
                setTimeout(() => {
                    updateDatepickerValue();
                }, 50);
            }
        });

        function initDatepicker() {
            const datepickerEl = document.querySelector('.datepicker');

            if (flatpickrInstance !== null && flatpickrInstance.element === datepickerEl) {
                updateDatepickerValue();
                return;
            }

            if (flatpickrInstance !== null) {
                flatpickrInstance.destroy();
                flatpickrInstance = null;
            }

            if (datepickerEl) {
                flatpickrInstance = flatpickr(datepickerEl, {
                    mode: "range",
                    dateFormat: "d/m/Y",
                    locale: "es",
                    rangeSeparator: " - ",
                    altInput: false,
                    allowInput: true,
                    disableMobile: true,
                    onChange: function(selectedDates, dateStr, instance) {                        
                        if (selectedDates.length !== 2) {
                            return;
                        }
                        
                        if (dateStr.includes(' a ')) {
                            dateStr = dateStr.replace(' a ', ' - ');
                        }
                                                
                        const livewireComponent = window.Livewire.find(datepickerEl.closest('[wire\\:id]').getAttribute('wire:id'));
                        if (livewireComponent) {
                            datepickerEl.setAttribute('data-updating', 'true');
                            livewireComponent.set('rangoFechas', dateStr);
                        }
                    },
                    onClose: function(selectedDates, dateStr, instance) {
                        datepickerEl.removeAttribute('data-updating');
                    }
                });

                updateDatepickerValue();
            }
        }

        function updateDatepickerValue() {
            const datepickerEl = document.querySelector('.datepicker');
            if (!datepickerEl || !flatpickrInstance) return;

            if (datepickerEl.getAttribute('data-updating') === 'true') {
                return;
            }

            const currentValue = datepickerEl.value;

            if (currentValue && currentValue.includes(' - ')) {
                const dates = currentValue.split(' - ').map(date => date.trim());
                if (dates.length === 2) {
                    try {
                        const parsedDates = dates.map(date => {
                            const parts = date.split('/');
                            return new Date(parts[2], parts[1] - 1, parts[0]);
                        });
                        
                        const currentDates = flatpickrInstance.selectedDates;
                        const needsUpdate = currentDates.length !== 2 || 
                            currentDates[0].getTime() !== parsedDates[0].getTime() ||
                            currentDates[1].getTime() !== parsedDates[1].getTime();

                        if (needsUpdate) {
                            flatpickrInstance.setDate(parsedDates, false);
                        }
                    } catch (e) {
                        console.error('❌ Error parseando fechas para datepicker:', e);
                    }
                }
            }
        }

        function initCharts() {
            const ctxGaugeMantenimiento = document.getElementById('gaugeMantenimiento');
            if (ctxGaugeMantenimiento && !chartMantenimiento) {
                chartMantenimiento = new Chart(ctxGaugeMantenimiento, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [{{ $porcentajeMantenimiento }}, 100 - {{ $porcentajeMantenimiento }}],
                            backgroundColor: ['#0075BF', '#e5e7eb'],
                            borderWidth: 0,
                            cutout: '80%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw: function(chart) {
                            const width = chart.width;
                            const height = chart.height;
                            const ctx = chart.ctx;

                            ctx.restore();
                            const fontSize = (height / 100).toFixed(2);
                            ctx.font = fontSize + "em sans-serif";
                            ctx.textBaseline = "middle";

                            const text = chart.data.datasets[0].data[0] + "%";
                            const textX = Math.round((width - ctx.measureText(text).width) / 2);
                            const textY = height / 2;

                            ctx.fillStyle = "#000";
                            ctx.fillText(text, textX, textY);
                            ctx.save();
                        }
                    }]
                });
            }

            const ctxGaugePrepagados = document.getElementById('gaugePrepagados');
            if (ctxGaugePrepagados && !chartPrepagados) {
                chartPrepagados = new Chart(ctxGaugePrepagados, {
                    type: 'doughnut',
                    data: {
                        datasets: [{
                            data: [{{ $porcentajePrepagados }}, 100 - {{ $porcentajePrepagados }}],
                            backgroundColor: ['#0075BF', '#e5e7eb'],
                            borderWidth: 0,
                            cutout: '80%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                enabled: false
                            }
                        }
                    },
                    plugins: [{
                        id: 'centerText',
                        afterDraw: function(chart) {
                            const width = chart.width;
                            const height = chart.height;
                            const ctx = chart.ctx;

                            ctx.restore();
                            const fontSize = (height / 100).toFixed(2);
                            ctx.font = fontSize + "em sans-serif";
                            ctx.textBaseline = "middle";

                            const text = chart.data.datasets[0].data[0] + "%";
                            const textX = Math.round((width - ctx.measureText(text).width) / 2);
                            const textY = height / 2;

                            ctx.fillStyle = "#000";
                            ctx.fillText(text, textX, textY);
                            ctx.save();
                        }
                    }]
                });
            }

            const ctxCantidadCitas = document.getElementById('chartCantidadCitas');
            if (ctxCantidadCitas && !chartCantidadCitas) {
                const labels = @json($datosCantidadCitas['labels'] ?? []);
                const generadas = @json($datosCantidadCitas['generadas'] ?? []);
                const efectivas = @json($datosCantidadCitas['efectivas'] ?? []);
                const porcentajesEfectividad = @json($datosCantidadCitas['porcentajesEfectividad'] ?? []);

                chartCantidadCitas = new Chart(ctxCantidadCitas, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'GENERADAS',
                                data: generadas,
                                backgroundColor: '#3b82f6',
                                borderColor: '#3b82f6',
                                borderWidth: 1,
                                order: 3
                            },
                            {
                                label: 'EFECTIVAS',
                                data: efectivas,
                                backgroundColor: '#3fb466ff',
                                borderColor: '#3fb466ff',
                                borderWidth: 1,
                                order: 3
                            },
                            {
                                label: '% EFECTIVIDAD',
                                data: porcentajesEfectividad,
                                type: 'line',
                                borderColor: '#aab41bff', // Color verde para efectividad
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                borderWidth: 2,
                                pointRadius: 4,
                                pointBackgroundColor: '#aab41bff',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointHoverRadius: 8,
                                fill: false,
                                tension: 0.3, // Suavizar la línea
                                order: 1,
                                yAxisID: 'y1', // Usar eje Y secundario para porcentajes
                                pointStyle: 'circle' // Estilo de punto circular
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        layout: {
                            padding: {
                                top: 30,
                                bottom: 20,
                                left: 10,
                                right: 10
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#374151',
                                borderWidth: 1,
                                cornerRadius: 6,
                                displayColors: true,
                                callbacks: {
                                    title: function(context) {
                                        return 'Período: ' + context[0].label;
                                    },
                                    label: function(context) {
                                        const label = context.dataset.label || '';
                                        const value = context.parsed.y;
                                        
                                        if (label === '% EFECTIVIDAD') {
                                            return `${label}: ${value}%`;
                                        }
                                        return `${label}: ${value} citas`;
                                    }
                                }
                            },
                            // Plugin personalizado para mostrar etiquetas de datos
                            datalabels: false // Desactivar el plugin datalabels si está presente
                        },

                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                beginAtZero: true,
                                grid: {
                                    display: true,
                                    color: '#e5e7eb'
                                },
                                title: {
                                    display: true,
                                    text: 'Cantidad de Citas',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                beginAtZero: true,
                                max: 100, // Máximo 100% para efectividad
                                grid: {
                                    drawOnChartArea: false, // No dibujar líneas de grid para evitar confusión
                                },
                                title: {
                                    display: true,
                                    text: '% Efectividad',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    },
                                    color: '#aab41bff'
                                },
                                ticks: {
                                    color: '#aab41bff',
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                title: {
                                    display: true,
                                    text: 'Período',
                                    font: {
                                        size: 12,
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    },
                    plugins: [{
                        id: 'dataLabels',
                        afterDatasetsDraw: function(chart) {
                            const ctx = chart.ctx;
                            
                            ctx.font = 'bold 10px Arial';
                            ctx.fillStyle = '#374151';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'bottom';

                            chart.data.datasets.forEach(function(dataset, datasetIndex) {
                                // Solo mostrar etiquetas para las barras (no para la línea de efectividad)
                                if (dataset.type !== 'line') {
                                    const meta = chart.getDatasetMeta(datasetIndex);
                                    if (meta.visible) {
                                        meta.data.forEach(function(bar, index) {
                                            const data = dataset.data[index];
                                            if (data > 0) { // Solo mostrar si el valor es mayor a 0
                                                ctx.fillText(data, bar.x, bar.y - 5);
                                            }
                                        });
                                    }
                                }
                            });
                        }
                    }]
                });
            }
        }

        function updateCharts() {
            const datosActuales = @json($datosCantidadCitas);
            
            updateChartsWithData({
                porcentajeMantenimiento: {{ $porcentajeMantenimiento }},
                porcentajePrepagados: {{ $porcentajePrepagados }},
                datosCantidadCitas: datosActuales
            });
        }

        function updateChartsWithData(data) {
            const ctxMantenimiento = document.getElementById('gaugeMantenimiento');
            const ctxPrepagados = document.getElementById('gaugePrepagados');
            const ctxCantidadCitas = document.getElementById('chartCantidadCitas');

            // Si los elementos no existen, los gráficos fueron destruidos por Livewire
            if (!ctxMantenimiento || !ctxPrepagados || !ctxCantidadCitas) {
                console.log('⚠️ Elementos del DOM no encontrados, reinicializando gráficos...');
                destroyCharts();
                initCharts();
                return;
            }

            if (chartMantenimiento && chartMantenimiento.canvas && chartMantenimiento.canvas.parentNode) {
                try {
                    const newPercentageMantenimiento = data.porcentajeMantenimiento || 0;
                    chartMantenimiento.data.datasets[0].data = [newPercentageMantenimiento, 100 - newPercentageMantenimiento];
                    chartMantenimiento.update('none');
                } catch (e) {
                    chartMantenimiento = null;
                }
            }

            if (chartPrepagados && chartPrepagados.canvas && chartPrepagados.canvas.parentNode) {
                try {
                    const newPercentagePrepagados = data.porcentajePrepagados || 0;
                    chartPrepagados.data.datasets[0].data = [newPercentagePrepagados, 100 - newPercentagePrepagados];
                    chartPrepagados.update('none');
                } catch (e) {
                    chartPrepagados = null;
                }
            }

            if (chartCantidadCitas && chartCantidadCitas.canvas && chartCantidadCitas.canvas.parentNode) {
                try {
                    const chartData = data.datosCantidadCitas || { labels: [], generadas: [], efectivas: [], porcentajesEfectividad: [] };
                    const newLabels = chartData.labels || [];
                    const newGeneradas = chartData.generadas || [];
                    const newEfectivas = chartData.efectivas || [];
                    const newPorcentajesEfectividad = chartData.porcentajesEfectividad || [];

                    chartCantidadCitas.data.labels = newLabels;
                    chartCantidadCitas.data.datasets[0].data = newGeneradas;
                    chartCantidadCitas.data.datasets[1].data = newEfectivas;
                    chartCantidadCitas.data.datasets[2].data = newPorcentajesEfectividad; // Usar porcentajes de efectividad
                    chartCantidadCitas.update('none');
                } catch (e) {
                    chartCantidadCitas = null;
                }
            }

            if (!chartMantenimiento || !chartPrepagados || !chartCantidadCitas) {
                initCharts();
            }
        }

        function destroyCharts() {
            if (chartMantenimiento) {
                chartMantenimiento.destroy();
                chartMantenimiento = null;
            }
            if (chartPrepagados) {
                chartPrepagados.destroy();
                chartPrepagados = null;
            }
            if (chartCantidadCitas) {
                chartCantidadCitas.destroy();
                chartCantidadCitas = null;
            }
            if (chartTiempoPromedio) {
                chartTiempoPromedio.destroy();
                chartTiempoPromedio = null;
            }
        }

        function updateMantenimientoChart(newPercentage) {
            if (chartMantenimiento && chartMantenimiento.canvas && chartMantenimiento.canvas.parentNode) {
                try {
                    chartMantenimiento.data.datasets[0].data = [newPercentage, 100 - newPercentage];
                    chartMantenimiento.update('none');
                } catch (e) {
                    console.error('❌ Error actualizando gráfico de mantenimiento:', e);
                }
            }
        }

        function updatePrepagadosChart(newPercentage) {
            if (chartPrepagados && chartPrepagados.canvas && chartPrepagados.canvas.parentNode) {
                try {
                    chartPrepagados.data.datasets[0].data = [newPercentage, 100 - newPercentage];
                    chartPrepagados.update('none');
                } catch (e) {
                    console.error('❌ Error actualizando gráfico de prepagados:', e);
                }
            }
        }

        window.updateAllCharts = function(data) {
            updateMantenimientoChart(data.porcentajeMantenimiento || 0);
            updatePrepagadosChart(data.porcentajePrepagados || 0);
        
            if (chartCantidadCitas && data.datosCantidadCitas) {
                const chartData = data.datosCantidadCitas;
                const generadas = chartData.generadas || [];
                const efectivas = chartData.efectivas || [];
                const porcentajesEfectividad = chartData.porcentajesEfectividad || [];
                
                chartCantidadCitas.data.labels = chartData.labels || [];
                chartCantidadCitas.data.datasets[0].data = generadas;
                chartCantidadCitas.data.datasets[1].data = efectivas;
                chartCantidadCitas.data.datasets[2].data = porcentajesEfectividad;
                chartCantidadCitas.update('none');
            }
        };

        function debugFiltros() {
            @this.call('debugFiltros');
        }

        function probarRangoConDatos() {
            @this.call('probarRango', '14/08/2025 - 15/08/2025');
        }
    </script>
</x-filament-panels::page>