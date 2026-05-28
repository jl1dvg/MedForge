<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmContact extends Model
{
    protected $table = 'crm_contacts';

    protected $fillable = [
        'patient_id', 'name', 'phone', 'email',
        'cedula', 'resolution', 'source',
    ];

    protected $casts = [
        'patient_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const RESOLUTION_PROVISIONAL = 'provisional';
    public const RESOLUTION_IDENTIFIED  = 'identified';
    public const RESOLUTION_LINKED      = 'linked';

    public const SOURCE_WHATSAPP  = 'whatsapp';
    public const SOURCE_SOLICITUD = 'solicitud';
    public const SOURCE_EXAMEN    = 'examen';
    public const SOURCE_MANUAL    = 'manual';

    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'contact_id');
    }

    public function scopeProvisional($query)
    {
        return $query->where('resolution', self::RESOLUTION_PROVISIONAL);
    }

    public function scopeByPhone($query, string $phone)
    {
        return $query->where('phone', $phone);
    }

    public function scopeByCedula($query, string $cedula)
    {
        return $query->where('cedula', $cedula);
    }
}
