<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmCalendarBlock
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string|null $doctor
 * @property string|null $sala
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property string|null $motivo
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * 
 * @property SolicitudProcedimiento $solicitud_procedimiento
 * @property User|null $user
 *
 * @package App\Models
 */
class CrmCalendarBlock extends Model
{
	protected $table = 'crm_calendar_blocks';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'fecha_inicio' => 'datetime',
		'fecha_fin' => 'datetime',
		'created_by' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'doctor',
		'sala',
		'fecha_inicio',
		'fecha_fin',
		'motivo',
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
