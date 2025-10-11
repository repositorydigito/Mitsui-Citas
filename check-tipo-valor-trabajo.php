<?php

/**
 * Script para verificar el estado de tipo_valor_trabajo de vehículos por RUC
 * 
 * Uso: php check-tipo-valor-trabajo.php [RUC]
 * Ejemplo: php check-tipo-valor-trabajo.php 20605414410
 */

require_once __DIR__.'/vendor/autoload.php';

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Obtener RUC desde argumentos de línea de comandos
$ruc = $argv[1] ?? null;

if (!$ruc) {
    echo "❌ Debes proporcionar un RUC\n";
    echo "Uso: php check-tipo-valor-trabajo.php [RUC]\n";
    echo "Ejemplo: php check-tipo-valor-trabajo.php 20605414410\n";
    exit(1);
}

echo "🔍 VERIFICACIÓN DE TIPO VALOR TRABAJO PARA RUC: {$ruc}\n";
echo str_repeat("━", 80) . "\n\n";

// Buscar usuario
$user = User::where('document_number', $ruc)->first();

if (!$user) {
    echo "❌ Usuario no encontrado con RUC: {$ruc}\n";
    exit(1);
}

echo "✅ Usuario encontrado: {$user->name} (ID: {$user->id})\n\n";

// Obtener vehículos del usuario
$vehiculos = Vehicle::where('user_id', $user->id)
    ->where('status', 'active')
    ->get();

if ($vehiculos->isEmpty()) {
    echo "⚠️  No se encontraron vehículos activos para este RUC\n";
    exit(1);
}

$total = $vehiculos->count();
echo "📊 Total de vehículos activos: {$total}\n\n";

// Estadísticas generales
$conTipoValor = $vehiculos->whereNotNull('tipo_valor_trabajo')->count();
$sinTipoValor = $vehiculos->whereNull('tipo_valor_trabajo')->count();
$conTipoValorVacio = $vehiculos->where('tipo_valor_trabajo', '')->count();

echo "📈 ESTADÍSTICAS GENERALES:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
printf("%-30s: %d (%.1f%%)\n", "Con tipo_valor_trabajo", $conTipoValor, ($conTipoValor/$total)*100);
printf("%-30s: %d (%.1f%%)\n", "Sin tipo_valor_trabajo (NULL)", $sinTipoValor, ($sinTipoValor/$total)*100);
printf("%-30s: %d (%.1f%%)\n", "Con tipo_valor_trabajo vacío", $conTipoValorVacio, ($conTipoValorVacio/$total)*100);
echo "\n";

// Distribución por marca
echo "🏷️  DISTRIBUCIÓN POR MARCA:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$porMarca = $vehiculos->groupBy('brand_name');
foreach ($porMarca as $marca => $vehiculosMarca) {
    $cantidadMarca = $vehiculosMarca->count();
    $conTipoMarca = $vehiculosMarca->whereNotNull('tipo_valor_trabajo')->where('tipo_valor_trabajo', '!=', '')->count();
    $sinTipoMarca = $cantidadMarca - $conTipoMarca;
    
    printf("%-15s: %4d total | %4d con tipo | %4d sin tipo (%.1f%% sin tipo)\n", 
        $marca ?: 'N/A', 
        $cantidadMarca, 
        $conTipoMarca, 
        $sinTipoMarca,
        ($sinTipoMarca/$cantidadMarca)*100
    );
}
echo "\n";

// Valores de tipo_valor_trabajo más comunes
echo "📋 VALORES MÁS COMUNES DE TIPO_VALOR_TRABAJO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$valoresComunes = $vehiculos->whereNotNull('tipo_valor_trabajo')
    ->where('tipo_valor_trabajo', '!=', '')
    ->groupBy('tipo_valor_trabajo')
    ->map->count()
    ->sortDesc()
    ->take(10);

if ($valoresComunes->isNotEmpty()) {
    foreach ($valoresComunes as $valor => $cantidad) {
        printf("%-20s: %d vehículos\n", $valor, $cantidad);
    }
} else {
    echo "   No hay valores definidos\n";
}
echo "\n";

// Mostrar algunos ejemplos de vehículos sin tipo_valor_trabajo
$vehiculosSinTipo = $vehiculos->filter(function($v) {
    return empty($v->tipo_valor_trabajo);
})->take(10);

if ($vehiculosSinTipo->isNotEmpty()) {
    echo "🚗 EJEMPLOS DE VEHÍCULOS SIN TIPO_VALOR_TRABAJO (primeros 10):\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    printf("%-12s %-15s %-20s %-10s %-15s\n", "ID", "Placa", "Modelo", "Año", "Marca");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($vehiculosSinTipo as $vehiculo) {
        printf("%-12s %-15s %-20s %-10s %-15s\n", 
            $vehiculo->id,
            $vehiculo->license_plate ?: 'N/A',
            substr($vehiculo->model ?: 'N/A', 0, 20),
            $vehiculo->year ?: 'N/A',
            $vehiculo->brand_name ?: 'N/A'
        );
    }
    echo "\n";
}

// Mostrar algunos ejemplos de vehículos CON tipo_valor_trabajo
$vehiculosConTipo = $vehiculos->filter(function($v) {
    return !empty($v->tipo_valor_trabajo);
})->take(10);

if ($vehiculosConTipo->isNotEmpty()) {
    echo "✅ EJEMPLOS DE VEHÍCULOS CON TIPO_VALOR_TRABAJO (primeros 10):\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    printf("%-12s %-15s %-20s %-10s %-15s %-15s\n", "ID", "Placa", "Modelo", "Año", "Marca", "Tipo Valor");
    echo str_repeat("-", 95) . "\n";
    
    foreach ($vehiculosConTipo as $vehiculo) {
        printf("%-12s %-15s %-20s %-10s %-15s %-15s\n", 
            $vehiculo->id,
            $vehiculo->license_plate ?: 'N/A',
            substr($vehiculo->model ?: 'N/A', 0, 20),
            $vehiculo->year ?: 'N/A',
            $vehiculo->brand_name ?: 'N/A',
            $vehiculo->tipo_valor_trabajo ?: 'VACÍO'
        );
    }
    echo "\n";
}

// Sugerencia de comandos
echo "🛠️  COMANDOS SUGERIDOS:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Para vista previa de actualizaciones:\n";
echo "   php artisan update:vehicles-by-ruc {$ruc} --dry-run\n\n";
echo "Para actualizar realmente (solo vehículos sin tipo_valor_trabajo):\n";
echo "   php artisan update:vehicles-by-ruc {$ruc}\n\n";
echo "Para forzar actualización de TODOS los vehículos:\n";
echo "   php artisan update:vehicles-by-ruc {$ruc} --force\n\n";

if ($sinTipoValor > 0 || $conTipoValorVacio > 0) {
    $necesitanActualizacion = $sinTipoValor + $conTipoValorVacio;
    echo "💡 RECOMENDACIÓN: Hay {$necesitanActualizacion} vehículos que necesitan tipo_valor_trabajo.\n";
    echo "   Ejecuta primero en modo --dry-run para ver qué se actualizaría.\n";
} else {
    echo "✅ ESTADO: Todos los vehículos ya tienen tipo_valor_trabajo definido.\n";
}

echo "\n" . str_repeat("━", 80) . "\n";
echo "✅ Verificación completada\n";