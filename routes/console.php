<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Hourly MarketRadar catalog import.
// Hoster.kz cron: * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
Schedule::command('netbazar:catalog-import')
    ->hourly()
    ->withoutOverlapping(30)
    ->runInBackground();
