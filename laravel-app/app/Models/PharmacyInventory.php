<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PharmacyInventory extends Model
{
    protected $table = 'pharmacy_inventory';

    protected $fillable = [
        'nombre',
        'principio_activo',
        'categoria',
        'presentacion',
        'stock',
        'stock_minimo',
        'precio',
        'estado',
    ];

    protected $casts = [
        'stock'       => 'int',
        'stock_minimo' => 'int',
        'precio'      => 'float',
    ];

    public function prescriptionItems(): HasMany
    {
        return $this->hasMany(PharmacyPrescriptionItem::class, 'inventory_id');
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }
}
