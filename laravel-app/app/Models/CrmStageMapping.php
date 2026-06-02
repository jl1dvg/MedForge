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

    /**
     * Returns the reverse mapping: crm_stage → source_state for a given source type.
     * When multiple source states map to the same CRM stage, returns the one that
     * represents the "lowest" (earliest) kanban position — to avoid over-advancing.
     * Priority is given to the first inserted row (lowest id).
     */
    public static function reverseForSourceType(string $sourceType): array
    {
        return Cache::remember(
            "crm_stage_mappings_reverse:{$sourceType}",
            self::CACHE_TTL,
            static fn () => static::query()
                ->where('source_type', $sourceType)
                ->where('is_active', true)
                ->orderBy('id') // first defined wins for each crm_stage
                ->get(['source_state', 'crm_stage'])
                ->unique('crm_stage')
                ->pluck('source_state', 'crm_stage')
                ->all()
        );
    }

    public static function clearCache(string $sourceType): void
    {
        Cache::forget("crm_stage_mappings:{$sourceType}");
        Cache::forget("crm_stage_mappings_reverse:{$sourceType}");
    }
}
