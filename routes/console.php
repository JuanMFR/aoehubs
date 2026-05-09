<?php

use Illuminate\Support\Facades\Schedule;

// Limpieza de matches zombie cada minuto. Requiere un cron del SO que dispare
// `php artisan schedule:run` cada minuto (en Windows se hace con Programador
// de tareas; en producción Linux es un cronjob estándar).
Schedule::command('matches:expire-stale')
    ->everyMinute()
    ->withoutOverlapping();

// Reintento de parseo para matches en pending_validation: corre cada hora.
// Cuando mgz upstream se actualice (o le metamos un patch nosotros), los
// matches que quedaron sin parsear se resuelven automáticamente acá.
Schedule::command('matches:reprocess-pending')
    ->hourly()
    ->withoutOverlapping();
