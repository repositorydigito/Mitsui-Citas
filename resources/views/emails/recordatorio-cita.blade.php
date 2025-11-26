<!DOCTYPE html>
<!--
    RICARDO - Template de email para recordatorios de citas 48h antes.
-->
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Cita</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: none;
        }
        .header {
            background-color: #17a2b8;
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .logo-container {
            margin-bottom: 20px;
        }
        .logo {
            max-width: 200px;
            height: auto;
            display: block;
            margin: 0 auto 15px;
            width: auto;
            border: 0;
            outline: none;
        }
        .content {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            line-height: 1.5;
        }
        .info-section {
            background-color: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 6px;
            border-left: 4px solid #17a2b8;
        }
        .info-label {
            font-weight: bold;
            color: #17a2b8;
            margin-bottom: 5px;
            display: block;
        }
        .info-value {
            color: #333;
            margin-bottom: 15px;
            display: block;
        }
        .reminder {
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 6px;
            margin: 25px 0;
            border: 1px solid #b8daff;
        }
        .urgent {
            background-color: #fff3cd;
            padding: 20px;
            border-radius: 6px;
            margin: 25px 0;
            border: 1px solid #ffc107;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 12px;
            line-height: 1.5;
        }
        .reminder-icon {
            color: #17a2b8;
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
        @media only screen and (max-width: 600px) {
            .content {
                padding: 20px 15px;
            }
            .header {
                padding: 20px 15px;
            }
            .logo {
                max-width: 180px;
            }
            .reminder, .info-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <div class="header">
        <div class="logo-container">
            @php
                // Usar URL absoluta directamente
                $logoUrl = url('images/logo_Mitsui_Blanco.png');
                $style = "display: block; margin: 0 auto 15px; width: 200px; height: auto;";
            @endphp

            <img src="{{ $logoUrl }}"
                 alt="Mitsui Automotriz"
                 class="logo"
                 style="{{ $style }}"
                 onerror="console.error('Error al cargar la imagen:', this.src)">
        </div>
        <h2 style="margin: 0; font-size: 24px; line-height: 1.3;">
            <span class="reminder-icon" style="color: #17a2b8; font-size: 24px; vertical-align: middle;">⏰</span>
            Recordatorio de Cita
        </h2>
        <p>{{ $appointment->appointment_number }}</p>
    </div>

    <div class="content">
        <p>Estimado/a <strong>{{ $datosCliente['nombres'] }} {{ $datosCliente['apellidos'] }}</strong>,</p>

        <p>Te recordamos que tienes una cita agendada para <strong>mañana</strong>.</p>

        <div class="urgent">
            <div class="info-label">⏰ Tu cita es MAÑANA:</div>
            <div class="info-value" style="font-size: 20px; font-weight: bold;">
                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y') }} a las {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i') }}
            </div>
        </div>

        <div class="info-section">
            <div class="info-label">🏢 Local:</div>
            <div class="info-value">{{ $appointment->premise->name ?? 'No especificado' }}</div>

            <div class="info-label">🚗 Vehículo:</div>
            <div class="info-value">{{ $datosVehiculo['marca'] ?? '' }} {{ $datosVehiculo['modelo'] ?? '' }} - Placa: {{ $datosVehiculo['placa'] ?? '' }}</div>

            <div class="info-label">🔧 Servicio:</div>
            <div class="info-value">
                @if($appointment->maintenance_type)
                    Mantenimiento periódico
                @elseif($appointment->service_mode)
                    {{ str_replace('Campañas / otros', 'Otros Servicios', $appointment->service_mode) }}
                @else
                    Mantenimiento periódico
                @endif
            </div>

            @if($appointment->maintenance_type)
            <div class="info-label">⚙️ Tipo de Mantenimiento:</div>
            <div class="info-value">{{ $appointment->maintenance_type }}</div>
            @endif

            @if($appointment->additionalServices && $appointment->additionalServices->count() > 0)
            <div class="info-label">🔧 Servicios Adicionales:</div>
            <div class="info-value">
                @foreach($appointment->additionalServices as $appointmentService)
                    <div style="margin-bottom: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 4px; border-left: 3px solid #17a2b8;">
                        <strong>{{ $appointmentService->additionalService->name ?? 'Servicio no encontrado' }}</strong>
                        @if($appointmentService->additionalService && $appointmentService->additionalService->description)
                            <div style="font-size: 11px; color: #888; margin-top: 2px; font-style: italic;">{{ $appointmentService->additionalService->description }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif

            @if($appointment->comments)
            <div class="info-label">💬 Comentarios:</div>
            <div class="info-value">{{ $appointment->comments }}</div>
            @endif
        </div>

        <div class="info-section">
            <div class="info-label">📞 Datos de Contacto:</div>
            <div class="info-value">
                <strong>Teléfono:</strong> {{ $datosCliente['celular'] }}<br>
                <strong>Email:</strong> {{ $datosCliente['email'] }}
            </div>
        </div>

        <div class="reminder">
            <p><strong>📋 Checklist para mañana:</strong></p>
            <ul>
                <li>✅ Llega 10 minutos antes de tu cita</li>
                <li>✅ Trae tu DNI original</li>
                <li>✅ Trae la tarjeta de propiedad del vehículo</li>
                <li>✅ Si tienes algún problema, llámanos con anticipación</li>
                <li>✅ Revisa que tengas combustible suficiente para llegar</li>
            </ul>
        </div>

        <div class="urgent">
            <p><strong>⚠️ ¿Necesitas reprogramar?</strong></p>
            <p>Si no puedes asistir mañana, por favor contáctanos lo antes posible para reprogramar tu cita.</p>
        </div>

        <p>¡Te esperamos mañana!</p>

        <p>Saludos cordiales,<br>
        <strong>Equipo de Servicio - Mitsui</strong></p>

        @php
            // Forzar HTTPS para la URL de la imagen
            $logoFooterUrl = asset('images/logomitsui2.svg');
            $logoFooterUrl = str_replace('http://', 'https://', $logoFooterUrl);
            $logoFooterStyle = "display: block; margin: 20px auto; width: 12rem; height: auto; max-width: 100%;";
        @endphp
        <img src="{{ $logoFooterUrl }}"
             alt="Mitsui Automotriz"
             style="{{ $logoFooterStyle }}"
             onerror="this.onerror=null; this.src='{{ asset('images/logo_Mitsui_Blanco.png') }}';">
    </div>

    <div class="footer">
        <p>Este es un recordatorio automático del sistema de agendamiento de citas.</p>
        <p>Fecha y hora: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
