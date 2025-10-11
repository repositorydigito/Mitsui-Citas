<?php

// Script para verificar vehículos en BD para el usuario con RUC 20605414410

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('document_number', '20605414410')->first();

if ($user) {
    echo "✅ Usuario encontrado:\n";
    echo "   Nombre: {$user->name}\n";
    echo "   ID: {$user->id}\n";
    echo "   Email: {$user->email}\n";
    echo "   Document: {$user->document_number}\n";
    echo "\n";
    
    $vehiculosCount = \App\Models\Vehicle::where('user_id', $user->id)
        ->where('status', 'active')
        ->count();
    
    echo "🚗 Vehículos en BD Local: {$vehiculosCount}\n";
    
    if ($vehiculosCount > 0) {
        echo "\n📊 Primeros 5 vehículos:\n";
        $vehiculos = \App\Models\Vehicle::where('user_id', $user->id)
            ->where('status', 'active')
            ->limit(5)
            ->get(['license_plate', 'brand_name', 'model', 'year']);
        
        foreach ($vehiculos as $v) {
            echo "   - {$v->license_plate} | {$v->brand_name} {$v->model} ({$v->year})\n";
        }
    }
} else {
    echo "❌ Usuario con RUC 20605414410 no encontrado en la base de datos\n";
}

echo "\n";
