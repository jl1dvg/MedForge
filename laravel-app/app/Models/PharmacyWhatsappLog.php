<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyWhatsappLog extends Model
{
    protected $table = 'pharmacy_whatsapp_logs';

    protected $fillable = [
        'pharmacy_patient_id',
        'pharmacy_prescription_id',
        'tipo',
        'mensaje',
        'numero_destino',
        'estado',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PharmacyPatient::class, 'pharmacy_patient_id');
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescription::class, 'pharmacy_prescription_id');
    }
}
