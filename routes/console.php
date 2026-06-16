<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mark expired plan subscriptions every 6 hours
Schedule::command('subscriptions:expire')->everySixHours();

// Send 24-hour appointment reminder emails (runs every 30 minutes for accuracy)
Schedule::command('appointments:send-reminders')->everyThirtyMinutes();

// Send welcome emails to CSV-imported customers (runs every minute, never repeats per user)
Schedule::command('customers:send-welcome-emails')->everyMinute();
