<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PharmacyPrescription extends Model
{
    protected $table = 'pharmacy_prescriptions';

    protected $fillable = [
        'pharmacy_patient_id',
        'external_id',
        'clinica',
        'medico',
        'estado',
        'notas',
        'fecha_prescripcion',
    ];

    protected $casts = [
        'fecha_prescripcion' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PharmacyPatient::class, 'pharmacy_patient_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PharmacyPrescriptionItem::class, 'pharmacy_prescription_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(PharmacyDelivery::class, 'pharmacy_prescription_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(PharmacyReminder::class, 'pharmacy_prescription_id');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(PharmacyWhatsappLog::class, 'pharmacy_prescription_id');
    }
}
