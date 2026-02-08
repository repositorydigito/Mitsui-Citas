<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->date('sap_fecha_ult_serv')->nullable()->after('frontend_states')->comment('Fecha último servicio desde SAP (PE_FEC_ULT_SERV)');
            $table->date('sap_fecha_factura')->nullable()->after('sap_fecha_ult_serv')->comment('Fecha factura desde SAP (PE_FEC_FACTURA)');
            $table->timestamp('sap_last_check_at')->nullable()->after('sap_fecha_factura')->comment('Última vez que se consultó SAP para esta cita');
            $table->index('sap_last_check_at', 'idx_appointments_sap_check');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_sap_check');
            $table->dropColumn(['sap_fecha_ult_serv', 'sap_fecha_factura', 'sap_last_check_at']);
        });
    }
};
