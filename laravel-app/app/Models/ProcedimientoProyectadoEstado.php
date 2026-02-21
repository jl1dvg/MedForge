<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientoProyectadoEstado
 * 
 * @property int $id
 * @property int $form_id
 * @property string $estado
 * @property Carbon $fecha_hora_cambio
 * 
 * @property ProcedimientoProyectado $procedimiento_proyectado
 *
 * @package App\Models
 */
class ProcedimientoProyectadoEstado extends Model
{
	protected $table = 'procedimiento_proyectado_estado';
	public $timestamps = false;

	protected $casts = [
		'form_id' => 'int',
		'fecha_hora_cambio' => 'datetime'
	];

	protected $fillable = [
		'form_id',
		'estado',
		'fecha_hora_cambio'
	];

	public function procedimiento_proyectado()
	{
		return $this->belongsTo(ProcedimientoProyectado::class, 'form_id', 'form_id');
	}
}
