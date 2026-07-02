<?php

namespace App\Modules\ControlCenter\Support;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

class ControlCenterTelemetryScheduler
{
    public function register(?Schedule $schedule = null): ?Event
    {
        if (! (bool) config('control_center.telemetry_enabled', false)) {
            return null;
        }

        $schedule ??= app(Schedule::class);

        return $schedule->command('control-center:send-telemetry')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }
}
