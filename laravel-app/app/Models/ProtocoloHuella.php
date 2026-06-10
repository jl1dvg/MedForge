<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ProtocoloHuella
 *
 * @property int $id
 * @property int|null $protocolo_id
 * @property int|null $usuario_id
 * @property string $evento
 * @property Carbon $creado_en
 * @property Carbon $actualizado_en
 *
 * @package App\Models
 */
class ProtocoloHuella extends Model
{
    protected $table = 'protocolo_huellas';
    public $timestamps = false;

    protected $casts = [
        'protocolo_id'   => 'int',
        'usuario_id'     => 'int',
        'creado_en'      => 'datetime',
        'actualizado_en' => 'datetime',
    ];

    protected $fillable = [
        'protocolo_id',
        'usuario_id',
        'evento',
        'creado_en',
        'actualizado_en',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
