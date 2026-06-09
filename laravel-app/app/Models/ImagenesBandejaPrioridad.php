<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bandeja de prioridad para exámenes de imágenes pendientes de informe.
 * Cada fila representa un procedimiento_proyectado marcado como urgente o pronto.
 * Hay como máximo una entrada activa por procedimiento (UNIQUE en procedimiento_id).
 *
 * @property int         $id
 * @property int         $procedimiento_id
 * @property string|null $form_id
 * @property string      $prioridad          'urgente' | 'pronto'
 * @property string|null $fecha_limite
 * @property string|null $responsable
 * @property string      $motivo
 * @property int|null    $solicitado_por
 * @property string|null $solicitado_nombre
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ImagenesBandejaPrioridad extends Model
{
    protected $table = 'imagenes_bandeja_prioridad';

    protected $fillable = [
        'procedimiento_id',
        'form_id',
        'prioridad',
        'fecha_limite',
        'responsable',
        'motivo',
        'solicitado_por',
        'solicitado_nombre',
    ];

    protected $casts = [
        'fecha_limite' => 'date:Y-m-d',
    ];
}
