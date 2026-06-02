<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmActivity extends Model
{
    protected $table = 'crm_activities';
    public $timestamps = false;

    protected $fillable = [
        'opportunity_id', 'type', 'description',
        'user_id', 'source_id', 'source_type',
    ];

    protected $casts = [
        'opportunity_id' => 'integer',
        'user_id'        => 'integer',
        'source_id'      => 'integer',
        'created_at'     => 'datetime',
    ];

    public const TYPE_NOTA           = 'nota';
    public const TYPE_LLAMADA        = 'llamada';
    public const TYPE_CAMBIO_ETAPA   = 'cambio_etapa';
    public const TYPE_EMAIL          = 'email';
    public const TYPE_EXAMEN         = 'examen';
    public const TYPE_SOLICITUD      = 'solicitud';
    public const TYPE_WHATSAPP       = 'whatsapp';
    public const TYPE_CONFLICTO_SYNC = 'conflicto_sync';

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }
}
