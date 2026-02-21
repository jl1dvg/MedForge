<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProtocoloDatum
 * 
 * @property int $id
 * @property string|null $hc_number
 * @property string|null $form_id
 * @property string|null $procedimiento_id
 * @property Carbon|null $fecha
 * @property string|null $cirujano_1
 * @property string|null $instrumentista
 * @property string|null $cirujano_2
 * @property string|null $circulante
 * @property string|null $primer_ayudante
 * @property string|null $anestesiologo
 * @property string|null $segundo_ayudante
 * @property string|null $ayudante_anestesia
 * @property string|null $tercer_ayudante
 * @property string|null $otros
 * @property string|null $membrete
 * @property string|null $dieresis
 * @property string|null $exposicion
 * @property string|null $hallazgo
 * @property string|null $operatorio
 * @property string|null $complicaciones_operatorio
 * @property string|null $datos_cirugia
 * @property array|null $procedimientos
 * @property string|null $lateralidad
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $hora_inicio
 * @property Carbon|null $fecha_fin
 * @property Carbon|null $hora_fin
 * @property string|null $tipo_anestesia
 * @property array|null $diagnosticos
 * @property array|null $diagnosticos_previos
 * @property bool|null $printed
 * @property bool|null $status
 * @property int|null $protocolo_firmado_por
 * @property Carbon|null $fecha_firma
 * @property int $version
 * @property array|null $insumos
 * @property array|null $medicamentos
 * 
 * @property PatientDatum|null $patient_datum
 * @property Collection|IplPlanificador[] $ipl_planificadors
 * @property Collection|ProtocoloInsumo[] $protocolo_insumos
 *
 * @package App\Models
 */
class ProtocoloDatum extends Model
{
	protected $table = 'protocolo_data';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime',
		'procedimientos' => 'json',
		'fecha_inicio' => 'datetime',
		'hora_inicio' => 'datetime',
		'fecha_fin' => 'datetime',
		'hora_fin' => 'datetime',
		'diagnosticos' => 'json',
		'diagnosticos_previos' => 'json',
		'printed' => 'bool',
		'status' => 'bool',
		'protocolo_firmado_por' => 'int',
		'fecha_firma' => 'datetime',
		'version' => 'int',
		'insumos' => 'json',
		'medicamentos' => 'json'
	];

	protected $fillable = [
		'hc_number',
		'form_id',
		'procedimiento_id',
		'fecha',
		'cirujano_1',
		'instrumentista',
		'cirujano_2',
		'circulante',
		'primer_ayudante',
		'anestesiologo',
		'segundo_ayudante',
		'ayudante_anestesia',
		'tercer_ayudante',
		'otros',
		'membrete',
		'dieresis',
		'exposicion',
		'hallazgo',
		'operatorio',
		'complicaciones_operatorio',
		'datos_cirugia',
		'procedimientos',
		'lateralidad',
		'fecha_inicio',
		'hora_inicio',
		'fecha_fin',
		'hora_fin',
		'tipo_anestesia',
		'diagnosticos',
		'diagnosticos_previos',
		'printed',
		'status',
		'protocolo_firmado_por',
		'fecha_firma',
		'version',
		'insumos',
		'medicamentos'
	];

	public function patient_datum()
	{
		return $this->belongsTo(PatientDatum::class, 'hc_number', 'hc_number');
	}

	public function ipl_planificadors()
	{
		return $this->hasMany(IplPlanificador::class, 'form_id_real', 'form_id');
	}

	public function protocolo_insumos()
	{
		return $this->hasMany(ProtocoloInsumo::class, 'protocolo_id');
	}
}
