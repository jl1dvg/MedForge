<?php

namespace App\Modules\Whatsapp\Support;

class WhatsappFeature
{
    public static function enabled(): bool
    {
        return (bool) config('whatsapp.migration.enabled', false);
    }

    public static function fallbackToLegacy(): bool
    {
        return (bool) config('whatsapp.migration.fallback_to_legacy', true);
    }

    public static function compareWithLegacy(): bool
    {
        return (bool) config('whatsapp.migration.compare_with_legacy', true);
    }

    public static function uiEnabled(): bool
    {
        return self::enabled() && (bool) config('whatsapp.migration.ui.enabled', false);
    }

    public static function apiReadEnabled(): bool
    {
        return self::enabled() && (bool) config('whatsapp.migration.api.read_enabled', false);
    }

    public static function apiWriteEnabled(): bool
    {
        return self::enabled() && (bool) config('whatsapp.migration.api.write_enabled', false);
    }

    public static function webhookEnabled(): bool
    {
        return self::enabled() && (bool) config('whatsapp.migration.api.webhook_enabled', false);
    }

    public static function sectionEnabled(string $section): bool
    {
        return match ($section) {
            'ui' => self::uiEnabled(),
            'api-read' => self::apiReadEnabled(),
            'api-write' => self::apiWriteEnabled(),
            'webhook' => self::webhookEnabled(),
            default => self::enabled(),
        };
    }
}
