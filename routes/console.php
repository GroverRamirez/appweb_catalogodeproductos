<?php

use App\Models\ProductView;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| El contenedor corre `schedule:run` cada 60 s (ver docker/supervisord.conf),
| así que estas tareas se ejecutan automáticamente en producción.
*/

// Backup diario de base de datos + archivos subidos (03:00).
// El script vive en /usr/local/bin/backup.sh dentro de la imagen.
Schedule::exec('/usr/local/bin/backup.sh')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground();

// Limpieza de jobs fallidos con más de 7 días, semanal.
Schedule::command('queue:prune-failed --hours=168')
    ->weekly();

// Purga del detalle de visitas antiguo (ver ProductView::prunable()).
// `producto_visitas` es un log de solo inserción y crece sin techo; el total
// acumulado por producto vive aparte en `productos.visitas`, así que esto no
// pierde el histórico.
Schedule::command('model:prune', ['--model' => [ProductView::class]])
    ->dailyAt('03:30');
