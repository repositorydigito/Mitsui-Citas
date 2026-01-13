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
     * Restaurar el campo 'brand' (marcas) manteniendo 'center_code' y 'available_days'
     */
    public function up(): void
    {
        // Esta migración ya no es necesaria porque en la migración del paso 1
        // decidimos NO eliminar la columna 'brand'.
        // Se mantiene el archivo para preservar la historia de migraciones.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('additional_services', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
