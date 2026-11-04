<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reservations:process-expirations')
    ->everyMinute()
    ->timezone('America/Lima')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('desego:backup --automatic --retention')
    ->dailyAt('03:00')
    ->timezone('America/Lima')
    ->withoutOverlapping();

Schedule::command('desego:prune-api-limits')
    ->dailyAt('03:30')
    ->timezone('America/Lima')
    ->withoutOverlapping();
