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
     * Cambiar center_codes (JSON array) a center_code (string único)
     * porque solo se permite seleccionar UN local por servicio
     */
    public function up(): void
    {
        // Primero agregar la nueva columna como nullable
        Schema::table('additional_services', function (Blueprint $table) {
            $table->string('center_code')->nullable()->after('code');
        });

        // NO migrar datos - dejar center_code como NULL por defecto
        // Los servicios existentes no tenían configurado un local específico,
        // por lo tanto deben aparecer en TODOS los locales (center_code = NULL)
        
        // Eliminar la columna antigua
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('center_codes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Agregar de vuelta center_codes
        Schema::table('additional_services', function (Blueprint $table) {
            $table->json('center_codes')->nullable()->after('code');
        });

        // Migrar datos de vuelta: convertir string a array
        $services = DB::table('additional_services')->get();

        foreach ($services as $service) {
            $centerCodes = $service->center_code ? [$service->center_code] : [];

            DB::table('additional_services')
                ->where('id', $service->id)
                ->update([
                    'center_codes' => json_encode($centerCodes),
                    'updated_at' => now(),
                ]);
        }

        Schema::table('additional_services', function (Blueprint $table) {
            $table->json('center_codes')->nullable(false)->change();
        });

        // Eliminar center_code
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('center_code');
        });
    }
};
