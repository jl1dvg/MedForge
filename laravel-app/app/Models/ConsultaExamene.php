<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ConsultaExamene
 * 
 * @property int $id
 * @property string $hc_number
 * @property string $form_id
 * @property Carbon|null $consulta_fecha
 * @property string|null $doctor
 * @property string|null $solicitante
 * @property string|null $examen_codigo
 * @property string $examen_nombre
 * @property string|null $lateralidad
 * @property string|null $prioridad
 * @property string|null $observaciones
 * @property string|null $derivacion_codigo
 * @property string|null $derivacion_pedido_id
 * @property string|null $derivacion_lateralidad
 * @property string|null $derivacion_fecha_vigencia_sel
 * @property string|null $derivacion_prefactura
 * @property string $estado
 * @property int|null $turno
 * @property int|null $crm_lead_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property ConsultaDatum $consulta_datum
 * @property Collection|ExamenCrmAdjunto[] $examen_crm_adjuntos
 * @property Collection|ExamenCrmCalendarBlock[] $examen_crm_calendar_blocks
 * @property ExamenCrmDetalle|null $examen_crm_detalle
 * @property Collection|ExamenCrmMetum[] $examen_crm_meta
 * @property Collection|ExamenCrmNota[] $examen_crm_notas
 * @property Collection|ExamenCrmTarea[] $examen_crm_tareas
 * @property Collection|ExamenEstadoLog[] $examen_estado_logs
 * @property Collection|ExamenMailLog[] $examen_mail_logs
 *
 * @package App\Models
 */
class ConsultaExamene extends Model
{
	protected $table = 'consulta_examenes';

	protected $casts = [
		'consulta_fecha' => 'datetime',
		'turno' => 'int',
		'crm_lead_id' => 'int'
	];

	protected $fillable = [
		'hc_number',
		'form_id',
		'consulta_fecha',
		'doctor',
		'solicitante',
		'examen_codigo',
		'examen_nombre',
		'lateralidad',
		'prioridad',
		'observaciones',
		'derivacion_codigo',
		'derivacion_pedido_id',
		'derivacion_lateralidad',
		'derivacion_fecha_vigencia_sel',
		'derivacion_prefactura',
		'estado',
		'turno',
		'crm_lead_id'
	];

	public function consulta_datum()
	{
		return $this->belongsTo(ConsultaDatum::class, 'form_id', 'form_id')
					->where('consulta_data.form_id', '=', 'consulta_examenes.form_id')
					->where('consulta_data.hc_number', '=', 'consulta_examenes.hc_number');
	}

	public function examen_crm_adjuntos()
	{
		return $this->hasMany(ExamenCrmAdjunto::class, 'examen_id');
	}

	public function examen_crm_calendar_blocks()
	{
		return $this->hasMany(ExamenCrmCalendarBlock::class, 'examen_id');
	}

	public function examen_crm_detalle()
	{
		return $this->hasOne(ExamenCrmDetalle::class, 'examen_id');
	}

	public function examen_crm_meta()
	{
		return $this->hasMany(ExamenCrmMetum::class, 'examen_id');
	}

	public function examen_crm_notas()
	{
		return $this->hasMany(ExamenCrmNota::class, 'examen_id');
	}

	public function examen_crm_tareas()
	{
		return $this->hasMany(ExamenCrmTarea::class, 'examen_id');
	}

	public function examen_estado_logs()
	{
		return $this->hasMany(ExamenEstadoLog::class, 'examen_id');
	}

	public function examen_mail_logs()
	{
		return $this->hasMany(ExamenMailLog::class, 'examen_id');
	}
}
