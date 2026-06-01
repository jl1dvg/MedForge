<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Schedule is managed in routes/console.php (Laravel 12 style).
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
