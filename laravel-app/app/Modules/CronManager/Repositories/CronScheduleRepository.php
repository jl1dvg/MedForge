<?php

declare(strict_types=1);

namespace App\Modules\CronManager\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CronScheduleRepository
{
    /**
     * @return Collection<int, object>
     */
    public function getAll(): Collection
    {
        return DB::table('cron_schedule')->orderByRaw("type = 'artisan' DESC")->orderBy('name')->get();
    }

    public function findBySlug(string $slug): ?object
    {
        return DB::table('cron_schedule')->where('slug', $slug)->first() ?: null;
    }

    public function update(string $slug, array $data): void
    {
        DB::table('cron_schedule')->where('slug', $slug)->update($data);
    }

    public function toggle(string $slug): void
    {
        DB::table('cron_schedule')
            ->where('slug', $slug)
            ->update(['enabled' => DB::raw('1 - enabled')]);
    }

    /**
     * @return Collection<int, object>
     */
    public function getEnabled(string $type): Collection
    {
        return DB::table('cron_schedule')
            ->where('type', $type)
            ->where('enabled', 1)
            ->get();
    }

    public function updateExecution(string $slug, string $status): void
    {
        DB::table('cron_schedule')
            ->where('slug', $slug)
            ->update([
                'last_run_at' => now(),
                'last_status' => $status,
            ]);
    }
}
