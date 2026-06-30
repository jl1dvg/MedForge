<?php

namespace App\Modules\Shared\Support;

use App\Models\Company;
use Carbon\Carbon;

class ReadOnlyMode
{
    public static function isActive(): bool
    {
        $company = static::resolveCompany();

        if ($company !== null) {
            if (!$company->is_active) {
                return true;
            }

            $mode = $company->service_mode;

            if ($mode === 'on') {
                return true;
            }

            if ($mode === 'off') {
                return false;
            }

            // auto: check date window stored on the company record
            if (!$company->readonly_start || !$company->readonly_end) {
                return false;
            }

            return Carbon::now()->between($company->readonly_start, $company->readonly_end);
        }

        // Fallback to config when no companies table exists yet (before migration
        // or in test environments without a seeded company row).
        $mode = config('medforge-readonly.mode', 'auto');

        if ($mode === 'on') {
            return true;
        }

        if ($mode === 'off') {
            return false;
        }

        $start = config('medforge-readonly.start_date');
        $end   = config('medforge-readonly.end_date');

        if (!$start || !$end) {
            return false;
        }

        return Carbon::now()->between(Carbon::parse($start), Carbon::parse($end));
    }

    public static function message(): string
    {
        $company = static::resolveCompany();

        if ($company !== null && $company->readonly_message) {
            return $company->readonly_message;
        }

        return (string) config('medforge-readonly.message', 'Sistema en modo solo lectura.');
    }

    private static function resolveCompany(): ?Company
    {
        try {
            return Company::first();
        } catch (\Throwable) {
            return null;
        }
    }
}
