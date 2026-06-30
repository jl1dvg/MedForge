<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyPatient extends Model
{
    protected $table = 'pharmacy_patients';

    protected $fillable = [
        'nombres',
        'apellidos',
        'identificacion',
        'telefono',
        'whatsapp',
        'email',
        'clinica',
        'medico_referidor',
        'notas',
    ];

    public function prescriptions(): HasMany
    {
        return $this->hasMany(PharmacyPrescription::class, 'pharmacy_patient_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(PharmacyReminder::class, 'pharmacy_patient_id');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(PharmacyWhatsappLog::class, 'pharmacy_patient_id');
    }

    public function getFullNameAttribute(): string
    {
        return $this->nombres . ' ' . $this->apellidos;
    }
}
