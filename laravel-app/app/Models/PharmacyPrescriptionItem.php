<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PharmacyPrescriptionItem extends Model
{
    protected $table = 'pharmacy_prescription_items';

    protected $fillable = [
        'pharmacy_prescription_id',
        'nombre_medicamento',
        'principio_activo',
        'presentacion',
        'dosis',
        'frecuencia',
        'duracion_dias',
        'indicaciones',
        'disponibilidad',
        'inventory_id',
    ];

    protected $casts = [
        'duracion_dias' => 'int',
        'inventory_id' => 'int',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(PharmacyPrescription::class, 'pharmacy_prescription_id');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(PharmacyInventory::class, 'inventory_id');
    }
}
