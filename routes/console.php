<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:outbox:publish')
    ->everyFiveSeconds()
    ->withoutOverlapping();

Schedule::command('bulk-imports:outbox:publish')
    ->everyFiveSeconds()
    ->withoutOverlapping();

Schedule::command('dashboard:outbox:publish')
    ->everyFiveSeconds()
    ->withoutOverlapping();