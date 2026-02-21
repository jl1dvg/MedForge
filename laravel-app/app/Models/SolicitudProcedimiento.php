<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudProcedimiento
 * 
 * @property int $id
 * @property string $hc_number
 * @property string $form_id
 * @property string|null $tipo
 * @property string|null $afiliacion
 * @property string|null $pedido_cirugia_id
 * @property string|null $procedimiento
 * @property string|null $doctor
 * @property Carbon|null $fecha
 * @property int|null $duracion
 * @property string|null $ojo
 * @property string|null $prioridad
 * @property string|null $producto
 * @property string|null $lente_id
 * @property string|null $lente_nombre
 * @property string|null $lente_poder
 * @property string|null $lente_observacion
 * @property string|null $incision
 * @property string|null $sigcenter_agenda_id
 * @property Carbon|null $sigcenter_fecha_inicio
 * @property string|null $sigcenter_trabajador_id
 * @property int|null $sigcenter_procedimiento_id
 * @property string|null $sigcenter_payload
 * @property string|null $sigcenter_response
 * @property string|null $derivacion_codigo
 * @property string|null $derivacion_pedido_id
 * @property string|null $derivacion_lateralidad
 * @property string|null $derivacion_fecha_vigencia_sel
 * @property string|null $derivacion_prefactura
 * @property string|null $observacion
 * @property string|null $sesiones
 * @property array|null $detalles_json
 * @property Carbon|null $created_at
 * @property int $secuencia
 * @property string $estado
 * @property int|null $turno
 * @property string|null $doctor_norm
 * 
 * @property Collection|AgendaCita[] $agenda_citas
 * @property Collection|CrmCalendarBlock[] $crm_calendar_blocks
 * @property Collection|SolicitudCrmAdjunto[] $solicitud_crm_adjuntos
 * @property SolicitudCrmDetalle|null $solicitud_crm_detalle
 * @property Collection|SolicitudCrmMetum[] $solicitud_crm_meta
 * @property Collection|SolicitudCrmNota[] $solicitud_crm_notas
 * @property Collection|SolicitudCrmTarea[] $solicitud_crm_tareas
 *
 * @package App\Models
 */
class SolicitudProcedimiento extends Model
{
	protected $table = 'solicitud_procedimiento';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime',
		'duracion' => 'int',
		'sigcenter_fecha_inicio' => 'datetime',
		'sigcenter_procedimiento_id' => 'int',
		'detalles_json' => 'json',
		'secuencia' => 'int',
		'turno' => 'int'
	];

	protected $fillable = [
		'hc_number',
		'form_id',
		'tipo',
		'afiliacion',
		'pedido_cirugia_id',
		'procedimiento',
		'doctor',
		'fecha',
		'duracion',
		'ojo',
		'prioridad',
		'producto',
		'lente_id',
		'lente_nombre',
		'lente_poder',
		'lente_observacion',
		'incision',
		'sigcenter_agenda_id',
		'sigcenter_fecha_inicio',
		'sigcenter_trabajador_id',
		'sigcenter_procedimiento_id',
		'sigcenter_payload',
		'sigcenter_response',
		'derivacion_codigo',
		'derivacion_pedido_id',
		'derivacion_lateralidad',
		'derivacion_fecha_vigencia_sel',
		'derivacion_prefactura',
		'observacion',
		'sesiones',
		'detalles_json',
		'secuencia',
		'estado',
		'turno',
		'doctor_norm'
	];

	public function agenda_citas()
	{
		return $this->hasMany(AgendaCita::class, 'solicitud_id');
	}

	public function crm_calendar_blocks()
	{
		return $this->hasMany(CrmCalendarBlock::class, 'solicitud_id');
	}

	public function solicitud_crm_adjuntos()
	{
		return $this->hasMany(SolicitudCrmAdjunto::class, 'solicitud_id');
	}

	public function solicitud_crm_detalle()
	{
		return $this->hasOne(SolicitudCrmDetalle::class, 'solicitud_id');
	}

	public function solicitud_crm_meta()
	{
		return $this->hasMany(SolicitudCrmMetum::class, 'solicitud_id');
	}

	public function solicitud_crm_notas()
	{
		return $this->hasMany(SolicitudCrmNota::class, 'solicitud_id');
	}

	public function solicitud_crm_tareas()
	{
		return $this->hasMany(SolicitudCrmTarea::class, 'solicitud_id');
	}
}
