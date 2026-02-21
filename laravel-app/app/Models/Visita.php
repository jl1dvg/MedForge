<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Visita
 * 
 * @property int $id
 * @property string $hc_number
 * @property Carbon $fecha_visita
 * @property Carbon|null $hora_llegada
 * @property string|null $usuario_registro
 * @property string|null $observaciones
 * 
 * @property PatientDatum $patient_datum
 * @property Collection|ProcedimientoProyectado[] $procedimiento_proyectados
 *
 * @package App\Models
 */
class Visita extends Model
{
	protected $table = 'visitas';
	public $timestamps = false;

	protected $casts = [
		'fecha_visita' => 'datetime',
		'hora_llegada' => 'datetime'
	];

	protected $fillable = [
		'hc_number',
		'fecha_visita',
		'hora_llegada',
		'usuario_registro',
		'observaciones'
	];

	public function patient_datum()
	{
		return $this->belongsTo(PatientDatum::class, 'hc_number', 'hc_number');
	}

	public function procedimiento_proyectados()
	{
		return $this->hasMany(ProcedimientoProyectado::class);
	}
}
