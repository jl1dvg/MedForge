<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudCrmTarea
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string $titulo
 * @property string|null $descripcion
 * @property string $estado
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property Carbon|null $due_date
 * @property Carbon|null $remind_at
 * @property Carbon|null $created_at
 * @property Carbon|null $completed_at
 * 
 * @property User|null $user
 * @property SolicitudProcedimiento $solicitud_procedimiento
 *
 * @package App\Models
 */
class SolicitudCrmTarea extends Model
{
	protected $table = 'solicitud_crm_tareas';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'assigned_to' => 'int',
		'created_by' => 'int',
		'due_date' => 'datetime',
		'remind_at' => 'datetime',
		'completed_at' => 'datetime'
	];

	protected $fillable = [
		'solicitud_id',
		'titulo',
		'descripcion',
		'estado',
		'assigned_to',
		'created_by',
		'due_date',
		'remind_at',
		'completed_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}
}
