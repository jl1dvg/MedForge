<?php

namespace App\Modules\Shared\Support;

use Carbon\Carbon;

class ReadOnlyMode
{
    public static function isActive(): bool
    {
        $mode = config('medforge-readonly.mode', 'auto');

        if ($mode === 'on') {
            return true;
        }

        if ($mode === 'off') {
            return false;
        }

        $start = config('medforge-readonly.start_date');
        $end = config('medforge-readonly.end_date');

        if (!$start || !$end) {
            return false;
        }

        return Carbon::now()->between(Carbon::parse($start), Carbon::parse($end));
    }

    public static function message(): string
    {
        return (string) config('medforge-readonly.message', 'Sistema en modo solo lectura.');
    }
}
