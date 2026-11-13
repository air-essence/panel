<?php

use Illuminate\Support\Facades\Schedule;

// Na serwerze cron uruchamia co minutę: php artisan schedule:run
Schedule::command('panel:synchronizuj-baselinker')
    ->everyFiveMinutes()
    ->withoutOverlapping(30)
    ->when(fn () => filled(config('services.baselinker.token')));
