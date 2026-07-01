<?php

namespace App\Modules\ControlCenter\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OperationalStateResolver
{
    public const PRODUCTION = 'production';
    public const MAINTENANCE = 'maintenance';
    public const READONLY = 'readonly';
    public const SUSPENDED = 'suspended';

    /**
     * @return array{state: string, instance_id: int|null, organization_id: int|null, slug: string|null, reason: string|null, source: string}
     */
    public function resolve(?string $slug = null): array
    {
        $slug = $slug ?: config('control_center.instance_slug');
        if (!is_string($slug) || trim($slug) === '') {
            return $this->fallback(null);
        }

        $slug = trim($slug);
        $cacheKey = 'control_center.operational_state.instance.' . $slug;
        $ttl = max(5, (int) config('control_center.state_cache_ttl', 60));

        try {
            return Cache::remember($cacheKey, $ttl, fn (): array => $this->loadState($slug));
        } catch (\Throwable) {
            try {
                $cached = Cache::get($cacheKey);
            } catch (\Throwable) {
                $cached = null;
            }

            if (is_array($cached) && isset($cached['state'])) {
                return $cached + ['source' => 'cache_fallback'];
            }

            return $this->fallback($slug);
        }
    }

    public function forget(?string $slug): void
    {
        if (!is_string($slug) || trim($slug) === '') {
            return;
        }

        try {
            Cache::forget('control_center.operational_state.instance.' . trim($slug));
        } catch (\Throwable) {
            // Operational state changes should not fail because cache storage is unavailable.
        }
    }

    /**
     * @return array{state: string, instance_id: int|null, organization_id: int|null, slug: string|null, reason: string|null, source: string}
     */
    private function loadState(string $slug): array
    {
        $instance = DB::table('control_center_instances')
            ->where('slug', $slug)
            ->first(['id', 'organization_id', 'slug', 'status']);

        if ($instance === null) {
            return $this->fallback($slug);
        }

        $now = Carbon::now();
        $state = DB::table('control_center_operational_states')
            ->where('instance_id', $instance->id)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first(['state', 'reason']);

        return [
            'state' => $this->normalizeState($state->state ?? $instance->status ?? self::PRODUCTION),
            'instance_id' => (int) $instance->id,
            'organization_id' => (int) $instance->organization_id,
            'slug' => (string) $instance->slug,
            'reason' => isset($state->reason) ? (string) $state->reason : null,
            'source' => 'database',
        ];
    }

    /**
     * @return array{state: string, instance_id: int|null, organization_id: int|null, slug: string|null, reason: string|null, source: string}
     */
    private function fallback(?string $slug): array
    {
        return [
            'state' => $this->normalizeState((string) config('control_center.fallback_state', self::PRODUCTION)),
            'instance_id' => null,
            'organization_id' => null,
            'slug' => $slug,
            'reason' => null,
            'source' => 'fallback',
        ];
    }

    private function normalizeState(string $state): string
    {
        return in_array($state, [self::PRODUCTION, self::MAINTENANCE, self::READONLY, self::SUSPENDED], true)
            ? $state
            : self::PRODUCTION;
    }
}
