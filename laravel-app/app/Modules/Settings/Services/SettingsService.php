<?php

declare(strict_types=1);

namespace App\Modules\Settings\Services;

use App\Models\AppSetting;
use App\Modules\Shared\Support\SettingsOptionResolver;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_TTL = 300; // 5 minutos
    private const CACHE_PREFIX = 'settings.v2.';

    /**
     * @return array<string,string>
     */
    public function getAll(): array
    {
        return AppSetting::query()
            ->pluck('value', 'name')
            ->map(static fn($v): string => (string) $v)
            ->all();
    }

    /**
     * @return array<string,string>
     */
    public function getByCategory(string $category): array
    {
        return AppSetting::query()
            ->where('category', $category)
            ->pluck('value', 'name')
            ->map(static fn($v): string => (string) $v)
            ->all();
    }

    public function getCached(string $name, string $default = ''): string
    {
        $key = self::CACHE_PREFIX . 'key.' . $name;

        /** @var string $value */
        $value = Cache::remember($key, self::CACHE_TTL, function () use ($name, $default): string {
            $setting = AppSetting::query()->where('name', $name)->first();
            return $setting !== null ? (string) $setting->value : $default;
        });

        return $value;
    }

    public function upsert(string $name, string $value, string $category, string $type = 'text'): void
    {
        AppSetting::query()->updateOrCreate(
            ['name' => $name],
            [
                'category' => $category,
                'value' => $value,
                'type' => $type,
                'autoload' => true,
            ]
        );

        if (str_starts_with($name, 'whatsapp_handoff_')) {
            Cache::forget('whatsapp.queue_open_status');
        }
    }

    /**
     * @param array<string,string> $payload
     */
    public function upsertBatch(array $payload, string $category): void
    {
        foreach ($payload as $name => $value) {
            $this->upsert($name, (string) $value, $category);
        }

        $this->invalidateCache($category);
    }

    public function invalidateCache(string $category): void
    {
        Cache::forget(self::CACHE_PREFIX . 'category.' . $category);

        $keys = AppSetting::query()
            ->where('category', $category)
            ->pluck('name');

        foreach ($keys as $name) {
            Cache::forget(self::CACHE_PREFIX . 'key.' . $name);
        }

        Cache::forget(self::CACHE_PREFIX . 'all');
        SettingsOptionResolver::flush();
    }
}
