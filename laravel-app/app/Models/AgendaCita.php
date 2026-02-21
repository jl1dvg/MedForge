<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AgendaCita
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string|null $sigcenter_agenda_id
 * @property string|null $sigcenter_pedido_id
 * @property string|null $sigcenter_factura_id
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_llegada
 * @property string|null $payload
 * @property string|null $response
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property SolicitudProcedimiento $solicitud_procedimiento
 * @property User|null $user
 *
 * @package App\Models
 */
class AgendaCita extends Model
{
	protected $table = 'agenda_citas';

	protected $casts = [
		'solicitud_id' => 'int',
		'fecha_inicio' => 'datetime',
		'fecha_llegada' => 'datetime',
		'created_by' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'sigcenter_agenda_id',
		'sigcenter_pedido_id',
		'sigcenter_factura_id',
		'fecha_inicio',
		'fecha_llegada',
		'payload',
		'response',
		'created_by'
	];

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}
