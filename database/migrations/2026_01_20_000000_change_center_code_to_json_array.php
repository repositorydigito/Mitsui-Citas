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
     * Cambiar center_code de VARCHAR (string único) a JSON (array múltiple)
     * para permitir que los servicios adicionales estén disponibles en múltiples locales
     */
    public function up(): void
    {
        // 1. Agregar nueva columna temporal como JSON
        Schema::table('additional_services', function (Blueprint $table) {
            $table->json('center_codes')->nullable()->after('center_code');
        });

        // 2. Migrar datos existentes de center_code (string) a center_codes (JSON array)
        $services = DB::table('additional_services')->get();

        foreach ($services as $service) {
            // Convertir el string único a un array
            // Si center_code está vacío o es null, usar array vacío
            $centerCodes = [];

            if (!empty($service->center_code)) {
                $centerCodes = [$service->center_code];
            }

            DB::table('additional_services')
                ->where('id', $service->id)
                ->update([
                    'center_codes' => json_encode($centerCodes),
                    'updated_at' => now(),
                ]);
        }

        // 3. Eliminar la columna antigua center_code
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('center_code');
        });

        // 4. Renombrar center_codes a center_code
        Schema::table('additional_services', function (Blueprint $table) {
            $table->renameColumn('center_codes', 'center_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Renombrar center_code a center_codes
        Schema::table('additional_services', function (Blueprint $table) {
            $table->renameColumn('center_code', 'center_codes');
        });

        // 2. Agregar columna center_code como VARCHAR
        Schema::table('additional_services', function (Blueprint $table) {
            $table->string('center_code')->nullable()->after('code');
        });

        // 3. Migrar datos de vuelta: tomar el primer elemento del array
        $services = DB::table('additional_services')->get();

        foreach ($services as $service) {
            $centerCodes = json_decode($service->center_codes, true);

            // Tomar el primer elemento del array, o null si está vacío
            $centerCode = !empty($centerCodes) && is_array($centerCodes) ? $centerCodes[0] : null;

            DB::table('additional_services')
                ->where('id', $service->id)
                ->update([
                    'center_code' => $centerCode,
                    'updated_at' => now(),
                ]);
        }

        // 4. Eliminar center_codes
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('center_codes');
        });
    }
};
