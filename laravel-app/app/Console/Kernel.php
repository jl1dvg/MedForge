<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $role = strtolower((string) (getenv('SERVER_ROLE') ?: ''));
        $isProduction = $role === 'production' || $role === '';

        if ($isProduction) {
            // Escalate stale operational opportunities to commercial team — runs daily at 08:00
            $schedule->command('crm:escalate')->dailyAt('08:00');

            // Auto-close WhatsApp handoffs with no activity in 7+ days — runs daily at 02:00
            $schedule->command('whatsapp:close-zombie-handoffs --days=7')->dailyAt('02:00');
        }
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
