<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientoEstadoTransicione
 * 
 * @property int|null $form_id
 * @property string|null $estado_inicio
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_fin
 * @property int|null $duracion_minutos
 * @property int|null $orden_estado
 *
 * @package App\Models
 */
class ProcedimientoEstadoTransicione extends Model
{
	protected $table = 'procedimiento_estado_transiciones';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'form_id' => 'int',
		'fecha_inicio' => 'datetime',
		'fecha_fin' => 'datetime',
		'duracion_minutos' => 'int',
		'orden_estado' => 'int'
	];

	protected $fillable = [
		'form_id',
		'estado_inicio',
		'fecha_inicio',
		'fecha_fin',
		'duracion_minutos',
		'orden_estado'
	];
}
