<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * RICARDO - Tabla para registrar recordatorios de citas enviados (email + WhatsApp).
     * Previene duplicados y permite tracking de envíos exitosos/fallidos.
     */
    public function up(): void
    {
        Schema::create('appointments_rescheduled', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id');
            $table->dateTime('reminder_date')->comment('Fecha y hora cuando debe enviarse el recordatorio (24h antes)');
            $table->enum('status_mail', ['pending', 'sent', 'failed'])->default('pending');
            $table->enum('status_notifications', ['pending', 'sent', 'failed'])->default('pending');
            $table->dateTime('sent_at')->nullable()->comment('Fecha y hora cuando se envió el recordatorio');
            $table->text('error_message')->nullable()->comment('Mensaje de error si falló el envío');
            $table->timestamps();

            // Foreign key
            $table->foreign('appointment_id')
                ->references('id')
                ->on('appointments')
                ->onDelete('cascade');

            // Índices
            $table->index('appointment_id');
            $table->index('reminder_date');
            $table->index(['status_mail', 'status_notifications']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments_rescheduled');
    }
};
