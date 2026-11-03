<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// HU-16 recovery: re-evaluates risk signals whose listener failed. Idempotent (active-alert dedupe).
// ponytail: scans all positive consultations/absences hourly; add a pending flag if volume grows.
Schedule::command('alertas:reevaluar')->hourly()->withoutOverlapping(55);
