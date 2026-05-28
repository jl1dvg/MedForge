<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CrmOpportunity extends Model
{
    protected $table = 'crm_opportunities';

    protected $fillable = [
        'contact_id', 'title', 'stage', 'source',
        'source_id', 'source_type', 'assigned_to', 'lost_reason',
    ];

    protected $casts = [
        'contact_id'  => 'integer',
        'source_id'   => 'integer',
        'assigned_to' => 'integer',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public const STAGE_NUEVO            = 'nuevo';
    public const STAGE_EN_CONTACTO      = 'en_contacto';
    public const STAGE_INTERESADO       = 'interesado';
    public const STAGE_PROPUESTA        = 'propuesta_enviada';
    public const STAGE_GANADO           = 'ganado';
    public const STAGE_PERDIDO          = 'perdido';

    public const STAGES = [
        self::STAGE_NUEVO,
        self::STAGE_EN_CONTACTO,
        self::STAGE_INTERESADO,
        self::STAGE_PROPUESTA,
        self::STAGE_GANADO,
        self::STAGE_PERDIDO,
    ];

    public const SOURCE_ENTRY_STAGE = [
        'whatsapp'  => self::STAGE_NUEVO,
        'solicitud' => self::STAGE_INTERESADO,
        'examen'    => self::STAGE_PROPUESTA,
        'manual'    => self::STAGE_NUEVO,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'opportunity_id')->orderBy('created_at', 'desc');
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo('sourceable', 'source_type', 'source_id');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('stage', [self::STAGE_GANADO, self::STAGE_PERDIDO]);
    }

    public function scopeByStage($query, string $stage)
    {
        return $query->where('stage', $stage);
    }

    public function scopeUrgent($query, int $waHours = 6, int $defaultHours = 48)
    {
        return $query->active()->where(function ($q) use ($waHours, $defaultHours): void {
            $q->where(function ($sub) use ($waHours): void {
                $sub->where('source', 'whatsapp')
                    ->where('updated_at', '<', now()->subHours($waHours));
            })->orWhere(function ($sub) use ($defaultHours): void {
                $sub->whereIn('source', ['solicitud', 'examen'])
                    ->where('updated_at', '<', now()->subHours($defaultHours));
            });
        });
    }
}
