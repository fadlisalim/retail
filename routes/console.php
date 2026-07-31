<?php

use App\Services\StockService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Release stock held by carts that entered payment but never completed.
Artisan::command('stock:release-expired', function (StockService $stock) {
    $released = $stock->expireStaleReservations();
    $this->info("Released {$released} expired stock reservation(s).");
})->purpose('Release expired stock reservations');

// Runs via the single system cron entry (see README → cron & queue).
Schedule::command('stock:release-expired')->everyFiveMinutes();

// Auto-cancel unpaid orders past the payment window (default 24h).
Schedule::command('orders:expire-unpaid')->hourly();

// Retention: prune old CS-assistant transcripts (30d) & stats rollups (180d).
Schedule::command('assistant:prune')->daily();

// Retention: prune the traffic-source log (default 90 days / ~3 months).
Schedule::command('visits:prune')->daily();
