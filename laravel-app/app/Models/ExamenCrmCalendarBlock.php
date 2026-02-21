<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmCalendarBlock
 * 
 * @property int $id
 * @property int $examen_id
 * @property string|null $doctor
 * @property string|null $sala
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property string|null $motivo
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * 
 * @property ConsultaExamene $consulta_examene
 * @property User|null $user
 *
 * @package App\Models
 */
class ExamenCrmCalendarBlock extends Model
{
	protected $table = 'examen_crm_calendar_blocks';
	public $timestamps = false;

	protected $casts = [
		'examen_id' => 'int',
		'fecha_inicio' => 'datetime',
		'fecha_fin' => 'datetime',
		'created_by' => 'int'
	];

	protected $fillable = [
		'examen_id',
		'doctor',
		'sala',
		'fecha_inicio',
		'fecha_fin',
		'motivo',
		'created_by'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}
