<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cita Confirmada</title>
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
            background-color: #0075BF;
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
            border-left: 4px solid #0075BF;
        }
        .info-label {
            font-weight: bold;
            color: #0075BF;
            margin-bottom: 5px;
            display: block;
        }
        .info-value {
            color: #333;
            margin-bottom: 15px;
            display: block;
        }
        .highlight {
            background-color: #e8f4fd;
            padding: 20px;
            border-radius: 6px;
            margin: 25px 0;
            border: 1px solid #0075BF;
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
        .success-icon {
            color: #28a745;
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
            .highlight, .info-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <div class="header">
        <div class="logo-container">
            @php
                $logoPath = 'images/logo_Mitsui_Blanco.png';
                
                // Intentar con base64 primero
                $base64Image = \App\Helpers\EmailImageHelper::imageToBase64($logoPath);
                
                // Si falla, usar URL absoluta
                $logoUrl = $base64Image ?: asset($logoPath);
                
                // Si es base64, forzar el ancho y alto
                $style = "display: block; margin: 0 auto 15px; max-width: 200px; height: auto;";
                if ($base64Image) {
                    $style .= " width: 200px; height: auto;";
                }
            @endphp
            
            <img src="{{ $logoUrl }}" 
                 alt="Mitsui Automotriz" 
                 class="logo"
                 style="{{ $style }}"
                 onerror="this.onerror=null; this.src='{{ asset($logoPath) }}';">
        </div>
        <h2 style="margin: 0; font-size: 24px; line-height: 1.3;">
            <span class="success-icon" style="color: #28a745; font-size: 24px; vertical-align: middle;">✅</span> 
            Cita Confirmada
        </h2>
    </div>
    
    <div class="content">
        <p>Hola, <strong>{{ $datosCliente['nombres'] }} {{ $datosCliente['apellidos'] }}</strong>,</p>
        <p>Tu cita de servicio fue agendada</p>
        <p>Gracias por tu preferencia, te compartimos los datos de tu cita de servicio.</p>
        <strong>DATOS DE LA CITA:</strong>

        <div class="highlight">
            <div class="info-label">📅 Fecha y Hora:</div>
            <div class="info-value" style="font-size: 18px; font-weight: bold;">
                {{ \Carbon\Carbon::parse($appointment->appointment_date)->format('d/m/Y') }} a las {{ \Carbon\Carbon::parse($appointment->appointment_time)->format('H:i') }}
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-label">🚗 Vehículo:</div>
            <div class="info-value">{{ $datosVehiculo['marca'] ?? '' }} {{ $datosVehiculo['modelo'] ?? '' }}</div>
            <div class="info-value">Placa: {{ $datosVehiculo['placa'] ?? '' }}</div>

            <div class="info-label">🏢 Local:</div>
            <div class="info-value">{{ $appointment->premise->name ?? 'No especificado' }}</div>

            <div class="info-label">🔧 Servicio:</div>
            <div class="info-value">{{ $appointment->service_type ?? 'Mantenimiento periódico' }}</div>
            
            @if($appointment->maintenance_type)
            <div class="info-label">⚙️ Mantenimiento:</div>
            <div class="info-value">{{ $appointment->maintenance_type }}</div>
            @endif
            
            @if($appointment->additionalServices && $appointment->additionalServices->count() > 0)
            <div class="info-label">🔧 Servicios Adicionales:</div>
            <div class="info-value">
                @foreach($appointment->additionalServices as $appointmentService)
                    <div style="margin-bottom: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 4px; border-left: 3px solid #0075BF;">
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
        
        <div class="highlight">
            <p><strong>📋 Importante:</strong></p>
            <ul>
                <p>Para brindarle un mejor servicio tenga presente las siguientes recomendaciones:</p>
                <li>Llegar 5 minutos antes de la hora de cita</li>
                <li>No traer o dejar objetos de valor en su unidad</li>
                <li>Portar los documentos de la unidad</li>
                <li>El Asesor de Servicio será quien confirme la fecha y hora de entrega de su unidad</li>
            </ul>
            <p>"Recuerde que, según el Decreto Legislativo 1529, las operaciones a partir de S/2,000 o US$ 500 se deberán realizar a través de un medio de pago dentro del sistema financiero, como transferencias bancarias o tarjetas (no aceptamos cheques)."</p>
        </div>
        
        <img src="{{ \App\Helpers\EmailImageHelper::imageToBase64('images/logomitsui2.svg') ?: url('images/logomitsui2.svg') }}" alt="logoMitsui2" style="display:flex; justify-content:center; width: 12rem; height: auto;">

    </div>
    
    <div class="footer">
        <p>Este es un correo automático. Por favor, no responda. Si tiene cualquier duda o sugerencia puede escribirnos a usuario@mitsuiautomotriz.com</p>
        <p>Por motivos de seguridad, las claves son secretas y únicamente deben ser conocidas por el propietario. En ningún caso, Mitsui Automotriz le solicitará información sobre su contraseña, códigos o datos de sus tarjetas afiliadas. Se recomienda comprobar siempre la dirección que aparece en la barra de navegación.</p>
    </div>
</body>
</html>