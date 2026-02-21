<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenEstadoLog
 * 
 * @property int $id
 * @property int $examen_id
 * @property string|null $estado_anterior
 * @property string $estado_nuevo
 * @property int|null $changed_by
 * @property string|null $origen
 * @property string|null $observacion
 * @property Carbon $changed_at
 * 
 * @property ConsultaExamene $consulta_examene
 * @property User|null $user
 *
 * @package App\Models
 */
class ExamenEstadoLog extends Model
{
	protected $table = 'examen_estado_log';
	public $timestamps = false;

	protected $casts = [
		'examen_id' => 'int',
		'changed_by' => 'int',
		'changed_at' => 'datetime'
	];

	protected $fillable = [
		'examen_id',
		'estado_anterior',
		'estado_nuevo',
		'changed_by',
		'origen',
		'observacion',
		'changed_at'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'changed_by');
	}
}
