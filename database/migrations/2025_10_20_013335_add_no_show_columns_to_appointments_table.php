<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            // ✅ Campo booleano para indicar si la cita fue no show
            $table->boolean('no_show')
                ->default(false)
                ->after('status')
                ->comment('Indica si la cita fue no show (no se presentó el cliente)');

            // ✅ Campo opcional para guardar la fecha/hora en que se marcó como no show
            $table->timestamp('no_show_at')
                ->nullable()
                ->after('no_show')
                ->comment('Fecha y hora en que la cita pasó a ser no show');
        });
    }

    /**
     * Revierten la migración.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['no_show', 'no_show_at']);
        });
    }
};

