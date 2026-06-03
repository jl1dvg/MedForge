<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CrmStageMapping extends Model
{
    protected $table = 'crm_stage_mappings';

    protected $fillable = ['source_type', 'source_state', 'crm_stage', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    private const CACHE_TTL = 300; // 5 minutes

    /** Returns all active mappings for a source type, keyed by source_state. */
    public static function forSourceType(string $sourceType): array
    {
        return Cache::remember(
            "crm_stage_mappings:{$sourceType}",
            self::CACHE_TTL,
            static fn () => static::query()
                ->where('source_type', $sourceType)
                ->where('is_active', true)
                ->pluck('crm_stage', 'source_state')
                ->all()
        );
    }

    public static function clearCache(string $sourceType): void
    {
        Cache::forget("crm_stage_mappings:{$sourceType}");
    }
}
