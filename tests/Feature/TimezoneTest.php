<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

test('the app runs on Bolivia time', function () {
    expect(config('app.timezone'))->toBe('America/La_Paz')
        ->and(now()->getTimezone()->getName())->toBe('America/La_Paz');
});

test('a sale at 21:00 in La Paz still counts for that day', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-07 21:00', 'America/La_Paz'));

    expect(now()->format('Y-m-d'))->toBe('2026-10-07');

    Carbon::setTestNow();
});

test('the exchange rate is fetched after the BCB publishes, in local time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command ?? '', 'tipo-cambio:actualizar'));

    $next = Carbon::instance($event->nextRunDate(Carbon::parse('2026-10-07 23:31', 'America/La_Paz')));

    // El BCB publica a las 23:30 hora boliviana; la consulta tiene que caer despues.
    expect($next->timezone('America/La_Paz')->format('Y-m-d H:i'))->toBe('2026-10-08 00:20');
});
