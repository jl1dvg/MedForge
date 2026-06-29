<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyReminder extends Model
{
    protected $table = 'pharmacy_reminders';

    protected $fillable = [
        'pharmacy_prescription_id',
        'pharmacy_patient_id',
        'pharmacy_prescription_item_id',
        'descripcion',
        'fecha_recordatorio',
        'estado',
        'notas',
    ];

    protected $casts = [
        'fecha_recordatorio' => 'date',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescription::class, 'pharmacy_prescription_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(PharmacyPatient::class, 'pharmacy_patient_id');
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescriptionItem::class, 'pharmacy_prescription_item_id');
    }
}
