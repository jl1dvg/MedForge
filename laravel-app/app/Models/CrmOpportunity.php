<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmOpportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'contact_id', 'title', 'stage', 'phase',
        'source', 'source_id', 'source_type',
        'afiliacion_tipo',
        'assigned_to', 'lost_reason',
        'last_activity_at', 'escalation_at',
        // Phase 1 — episode schema
        'procedure_group', 'lateralidad', 'episode_started_at',
        'previous_opportunity_id', 'opportunity_type', 'continuity_flag',
    ];

    protected $casts = [
        'contact_id'             => 'integer',
        'source_id'              => 'integer',
        'assigned_to'            => 'integer',
        'last_activity_at'       => 'datetime',
        'escalation_at'          => 'datetime',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
        // Phase 1 — episode schema
        'episode_started_at'     => 'datetime',
        'previous_opportunity_id'=> 'integer',
        'continuity_flag'        => 'boolean',
    ];

    // Stages — Phase 1: operational
    public const STAGE_NUEVO        = 'nuevo';
    public const STAGE_CONTACTADO   = 'contactado';
    public const STAGE_EN_EVALUACION = 'en_evaluacion';
    // Stages — Phase 2: commercial
    public const STAGE_PROPUESTA    = 'propuesta';
    public const STAGE_COMPROMETIDO = 'comprometido';
    public const STAGE_GANADO       = 'ganado';
    public const STAGE_PERDIDO      = 'perdido';

    public const STAGES = [
        self::STAGE_NUEVO, self::STAGE_CONTACTADO, self::STAGE_EN_EVALUACION,
        self::STAGE_PROPUESTA, self::STAGE_COMPROMETIDO,
        self::STAGE_GANADO, self::STAGE_PERDIDO,
    ];

    public const COMMERCIAL_STAGES = [
        self::STAGE_PROPUESTA, self::STAGE_COMPROMETIDO,
        self::STAGE_GANADO, self::STAGE_PERDIDO,
    ];

    public const PHASE_OPERATIONAL = 'operational';
    public const PHASE_COMMERCIAL  = 'commercial';

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id')->orderBy('created_at', 'desc');
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(CrmProposal::class, 'crm_opportunity_id')->orderBy('created_at', 'desc');
    }

    public function previousOpportunity(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_opportunity_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_GANADO, self::STAGE_PERDIDO]);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeByPhase($query, string $phase)
    {
        return $query->where('phase', $phase);
    }

    /** Opportunities that should have auto-escalated already. */
    public function scopePendingEscalation($query)
    {
        return $query->where('phase', self::PHASE_OPERATIONAL)
            ->whereNotNull('escalation_at')
            ->where('escalation_at', '<=', now());
    }

    /** Opportunities with no activity for longer than the given hours. */
    public function scopeStaleFor($query, int $hours)
    {
        return $query->active()->where(function ($q) use ($hours): void {
            $q->where('last_activity_at', '<', now()->subHours($hours))
              ->orWhereNull('last_activity_at');
        });
    }
}
