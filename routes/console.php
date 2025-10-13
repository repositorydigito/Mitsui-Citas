<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\UpdateComodinUsersC4CIdJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar job para actualizar usuarios comodín cada 5 minutos
Schedule::job(new UpdateComodinUsersC4CIdJob)->everyMinute();

// Comandos críticos cada minuto
Schedule::command('appointment:sync --all')->everyMinute();
Schedule::command('appointments:update-package-ids --sync')->everyMinute();
// DESACTIVADO: Comando consume muchos recursos
// Schedule::command('vehicles:update-tipo-valor-trabajo')->everyMinute();
