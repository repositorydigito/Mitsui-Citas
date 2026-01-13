<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Reemplaza el campo 'brand' por 'center_codes' y agrega 'available_days'
     * para permitir que los servicios adicionales se asignen a locales específicos
     * y días de la semana específicos.
     */
    public function up(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            // Agregar nuevas columnas
            $table->json('center_codes')->nullable()->after('code')->comment('Códigos de centros/locales donde está disponible el servicio');
            $table->json('available_days')->nullable()->after('center_codes')->comment('Días de la semana disponibles: monday, tuesday, wednesday, thursday, friday, saturday, sunday');
        });

        // NO migrar datos de brand a center_codes (center_codes quedará vacío)
        
        // Inicializar available_days con todos los días por defecto
        $this->initializeAvailableDays();

        // NO eliminar la columna brand (conservar datos existentes)
        
        // Hacer available_days NOT NULL
        Schema::table('additional_services', function (Blueprint $table) {
            $table->json('available_days')->nullable(false)->change();
            // center_codes se mantiene nullable
        });
    }

    /**
     * Migrar datos de brand a center_codes
     *
     * Lógica:
     * - Obtener todos los centros de la tabla premises agrupados por marca
     * - Para cada servicio adicional, convertir sus marcas a códigos de centros
     */
    protected function migrateBrandToCenterCodes(): void
    {
        // Obtener mapeo de marcas a códigos de centros desde premises
        $toyotaCenters = DB::table('premises')
            ->where('brand', 'Toyota')
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        $lexusCenters = DB::table('premises')
            ->where('brand', 'Lexus')
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        $hinoCenters = DB::table('premises')
            ->where('brand', 'Hino')
            ->where('is_active', true)
            ->pluck('code')
            ->toArray();

        // Mapeo de marcas a centros
        $brandToCentersMap = [
            'Toyota' => $toyotaCenters,
            'Lexus' => $lexusCenters,
            'Hino' => $hinoCenters,
        ];

        // Obtener todos los servicios adicionales
        $services = DB::table('additional_services')->get();

        foreach ($services as $service) {
            // Decodificar el campo brand (es JSON)
            $brands = json_decode($service->brand, true);

            if (!is_array($brands)) {
                // Si no es array, intentar convertirlo
                $brands = [$brands];
            }

            // Recopilar todos los códigos de centros para las marcas del servicio
            $centerCodes = [];
            foreach ($brands as $brand) {
                if (isset($brandToCentersMap[$brand])) {
                    $centerCodes = array_merge($centerCodes, $brandToCentersMap[$brand]);
                }
            }

            // Eliminar duplicados y reindexar
            $centerCodes = array_values(array_unique($centerCodes));

            // Si no hay centros, usar un array vacío (se manejará después)
            if (empty($centerCodes)) {
                $centerCodes = [];
            }

            // Actualizar el servicio con los códigos de centros
            DB::table('additional_services')
                ->where('id', $service->id)
                ->update([
                    'center_codes' => json_encode($centerCodes),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Inicializar available_days con todos los días de la semana por defecto
     */
    protected function initializeAvailableDays(): void
    {
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        DB::table('additional_services')
            ->update([
                'available_days' => json_encode($allDays),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * IMPORTANTE: Esta reversión NO puede recuperar exactamente los datos originales
     * porque la conversión de center_codes a brand es una aproximación.
     */
    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            // Agregar de vuelta la columna brand
            $table->json('brand')->nullable()->after('code');
        });

        // Intentar revertir center_codes a brand (aproximación)
        $this->revertCenterCodesToBrand();

        // Eliminar las nuevas columnas
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn(['center_codes', 'available_days']);
        });

        // Hacer brand NOT NULL
        Schema::table('additional_services', function (Blueprint $table) {
            $table->json('brand')->nullable(false)->change();
        });
    }

    /**
     * Intentar revertir center_codes a brand
     *
     * Lógica inversa (aproximación):
     * - Para cada servicio, obtener las marcas de los centros asignados
     * - Agrupar marcas únicas
     */
    protected function revertCenterCodesToBrand(): void
    {
        $services = DB::table('additional_services')->get();

        foreach ($services as $service) {
            $centerCodes = json_decode($service->center_codes, true);

            if (!is_array($centerCodes) || empty($centerCodes)) {
                // Si no hay centros, asignar Toyota por defecto
                DB::table('additional_services')
                    ->where('id', $service->id)
                    ->update([
                        'brand' => json_encode(['Toyota']),
                        'updated_at' => now(),
                    ]);
                continue;
            }

            // Obtener marcas de los centros
            $brands = DB::table('premises')
                ->whereIn('code', $centerCodes)
                ->pluck('brand')
                ->unique()
                ->values()
                ->toArray();

            // Si no hay marcas encontradas, usar Toyota por defecto
            if (empty($brands)) {
                $brands = ['Toyota'];
            }

            DB::table('additional_services')
                ->where('id', $service->id)
                ->update([
                    'brand' => json_encode($brands),
                    'updated_at' => now(),
                ]);
        }
    }
};
