<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MarkNoShowAppointmentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tiempo máximo de ejecución (en segundos)
     */
    public $timeout = 300;

    /**
     * Ejecutar el Job
     */
    public function handle(): void
    {
        Log::info('🕒 Ejecutando MarkNoShowAppointmentsJob...');

        $sql = "
            UPDATE appointments
            SET 
                no_show = TRUE,
                no_show_at = NOW()
            WHERE 
                status = 'confirmed'
                AND no_show = FALSE
                AND (
                    (
                        -- CASO 1: Confirmada pero sin trabajo activo/completado en 10h
                        JSON_EXTRACT(frontend_states, '$.cita_confirmada') IS NOT NULL
                        AND (
                            JSON_EXTRACT(frontend_states, '$.en_trabajo.activo') IS NULL
                            OR JSON_EXTRACT(frontend_states, '$.en_trabajo.activo') = false
                        )
                        AND (
                            JSON_EXTRACT(frontend_states, '$.en_trabajo.completado') IS NULL
                            OR JSON_EXTRACT(frontend_states, '$.en_trabajo.completado') = false
                        )
                        AND TIMESTAMPDIFF(
                            HOUR,
                            STR_TO_DATE(
                                JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.cita_confirmada.timestamp')),
                                '%Y-%m-%d %H:%i:%s'
                            ),
                            NOW()
                        ) > 10
                    )
                    OR
                    (
                        -- CASO 2: Confirmada + trabajo, pero pasaron >10h entre ambos
                        JSON_EXTRACT(frontend_states, '$.cita_confirmada') IS NOT NULL
                        AND (
                            JSON_EXTRACT(frontend_states, '$.en_trabajo.activo') = true
                            OR JSON_EXTRACT(frontend_states, '$.en_trabajo.completado') = true
                        )
                        AND TIMESTAMPDIFF(
                            HOUR,
                            STR_TO_DATE(
                                JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.cita_confirmada.timestamp')),
                                '%Y-%m-%d %H:%i:%s'
                            ),
                            STR_TO_DATE(
                                JSON_UNQUOTE(JSON_EXTRACT(frontend_states, '$.en_trabajo.timestamp')),
                                '%Y-%m-%d %H:%i:%s'
                            )
                        ) > 10
                    )
                );
        ";

        $affected = DB::affectingStatement($sql);

        Log::info("✅ MarkNoShowAppointmentsJob completado. Citas marcadas: {$affected}");
    }
}
