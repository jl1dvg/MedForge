<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmIntentLead extends Model
{
    protected $table = 'crm_intent_leads';

    protected $fillable = [
        'contact_id',
        'source',
        'source_id',
        'source_type',
        'motivo',
        'assigned_to',
        'status',
        'opportunity_id',
        'converted_at',
        'discarded_at',
    ];

    protected $casts = [
        'contact_id'     => 'integer',
        'source_id'      => 'integer',
        'assigned_to'    => 'integer',
        'opportunity_id' => 'integer',
        'converted_at'   => 'datetime',
        'discarded_at'   => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];

    public const STATUS_NUEVO      = 'nuevo';
    public const STATUS_CONTACTADO = 'contactado';
    public const STATUS_CALIFICADO = 'calificado';
    public const STATUS_CONVERTIDO = 'convertido';
    public const STATUS_DESCARTADO = 'descartado';

    public const STATUSES = [
        self::STATUS_NUEVO,
        self::STATUS_CONTACTADO,
        self::STATUS_CALIFICADO,
        self::STATUS_CONVERTIDO,
        self::STATUS_DESCARTADO,
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'contact_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function scopePending($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CONVERTIDO, self::STATUS_DESCARTADO]);
    }
}
