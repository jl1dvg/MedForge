<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $solicitud_id
 * @property string|null $estado_anterior
 * @property string $estado_nuevo
 * @property int|null $user_id
 * @property string|null $nota
 * @property string $origen
 * @property Carbon $created_at
 */
class SolicitudEstadoLog extends Model
{
    protected $table = 'solicitud_estado_log';
    public $timestamps = false;

    protected $casts = [
        'solicitud_id' => 'int',
        'user_id' => 'int',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'solicitud_id',
        'estado_anterior',
        'estado_nuevo',
        'user_id',
        'nota',
        'origen',
    ];
}
