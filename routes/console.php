<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Periodically evaluate forward-pick face bin deficits and generate replenishment tasks
Schedule::command('wms:evaluate-replenishments')->everyFifteenMinutes();

// Periodically dispatch pending integration outbox events to the companion NestJS MES
Schedule::command('wms:sync-mes-outbox')->everyMinute();
