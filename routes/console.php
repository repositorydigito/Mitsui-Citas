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

// Job para marcar citas como no-show cada hora
Schedule::command('appointments:mark-no-show')->hourly();

// Enviar recordatorios de citas todos los días a las 9:00 AM
Schedule::command('citas:enviar-recordatorios')
        ->dailyAt('09:00')
        ->withoutOverlapping()
        ->runInBackground();

// DESACTIVADO: Comando consume muchos recursos
// Schedule::command('vehicles:update-tipo-valor-trabajo')->everyMinute();

// RICARDO - Sistema de recordatorios 48h antes (Email + WhatsApp).
Schedule::command('appointments:send-reminders')
        ->dailyAt('00:00')
        ->timezone('America/Lima')
        ->withoutOverlapping()
        ->runInBackground();
