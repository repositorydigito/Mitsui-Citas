<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkNoShowAppointments extends Command
{
    protected $signature = 'appointments:mark-no-show';
    protected $description = 'Marca como no show las citas confirmadas que cumplan la condición de 10 horas sin pasar a en_trabajo';

    public function handle(): int
    {
        $this->info('🔍 Buscando citas para marcar como no show...');

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
                        -- 🧩 CASO 1: Confirmada, sin trabajo activo ni completado en 10h
                        JSON_EXTRACT(frontend_states, '$.cita_confirmada.timestamp') IS NOT NULL
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
                        -- 🧩 CASO 2: Tiene cita_confirmada y en_trabajo, pero pasaron >10h entre ambos
                        JSON_EXTRACT(frontend_states, '$.cita_confirmada.timestamp') IS NOT NULL
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

        $this->info("✅ Se marcaron {$affected} citas como no show.");

        return Command::SUCCESS;
    }
}
