<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyDelivery extends Model
{
    protected $table = 'pharmacy_deliveries';

    protected $fillable = [
        'pharmacy_prescription_id',
        'estado',
        'direccion',
        'observacion',
        'fecha_programada',
        'fecha_entrega',
        'responsable',
    ];

    protected $casts = [
        'fecha_programada' => 'date',
        'fecha_entrega'    => 'datetime',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescription::class, 'pharmacy_prescription_id');
    }
}
