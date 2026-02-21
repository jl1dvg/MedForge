<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientoProyectado
 * 
 * @property int $id
 * @property int $form_id
 * @property string $procedimiento_proyectado
 * @property string|null $doctor
 * @property string $hc_number
 * @property string|null $sede_departamento
 * @property int|null $id_sede
 * @property string|null $estado_agenda
 * @property string|null $afiliacion
 * @property Carbon|null $fecha
 * @property Carbon|null $hora
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $visita_id
 * 
 * @property Visita|null $visita
 * @property PatientDatum $patient_datum
 * @property Collection|DiagnosticosAsignado[] $diagnosticos_asignados
 * @property Collection|ProcedimientoProyectadoEstado[] $procedimiento_proyectado_estados
 *
 * @package App\Models
 */
class ProcedimientoProyectado extends Model
{
	protected $table = 'procedimiento_proyectado';

	protected $casts = [
		'form_id' => 'int',
		'id_sede' => 'int',
		'fecha' => 'datetime',
		'hora' => 'datetime',
		'visita_id' => 'int'
	];

	protected $fillable = [
		'form_id',
		'procedimiento_proyectado',
		'doctor',
		'hc_number',
		'sede_departamento',
		'id_sede',
		'estado_agenda',
		'afiliacion',
		'fecha',
		'hora',
		'visita_id'
	];

	public function visita()
	{
		return $this->belongsTo(Visita::class);
	}

	public function patient_datum()
	{
		return $this->belongsTo(PatientDatum::class, 'hc_number', 'hc_number');
	}

	public function diagnosticos_asignados()
	{
		return $this->hasMany(DiagnosticosAsignado::class, 'form_id', 'form_id');
	}

	public function procedimiento_proyectado_estados()
	{
		return $this->hasMany(ProcedimientoProyectadoEstado::class, 'form_id', 'form_id');
	}
}
