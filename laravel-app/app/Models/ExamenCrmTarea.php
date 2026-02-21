<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmTarea
 * 
 * @property int $id
 * @property int $examen_id
 * @property string $titulo
 * @property string|null $descripcion
 * @property string $estado
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property Carbon|null $due_date
 * @property Carbon|null $remind_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property User|null $user
 * @property ConsultaExamene $consulta_examene
 *
 * @package App\Models
 */
class ExamenCrmTarea extends Model
{
	protected $table = 'examen_crm_tareas';

	protected $casts = [
		'examen_id' => 'int',
		'assigned_to' => 'int',
		'created_by' => 'int',
		'due_date' => 'datetime',
		'remind_at' => 'datetime'
	];

	protected $fillable = [
		'examen_id',
		'titulo',
		'descripcion',
		'estado',
		'assigned_to',
		'created_by',
		'due_date',
		'remind_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}
}
