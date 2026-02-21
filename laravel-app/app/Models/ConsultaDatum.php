<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ConsultaDatum
 * 
 * @property int $id
 * @property string|null $hc_number
 * @property string|null $form_id
 * @property Carbon $fecha
 * @property string $motivo_consulta
 * @property string|null $enfermedad_actual
 * @property string|null $examen_fisico
 * @property string|null $plan
 * @property int|null $estado_enfermedad
 * @property string|null $antecedente_alergico
 * @property string|null $signos_alarma
 * @property string|null $recomen_no_farmaco
 * @property Carbon|null $vigencia_receta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property array|null $diagnosticos
 * @property array|null $examenes
 * 
 * @property PatientDatum|null $patient_datum
 * @property Collection|ConsultaExamene[] $consulta_examenes
 *
 * @package App\Models
 */
class ConsultaDatum extends Model
{
	protected $table = 'consulta_data';

	protected $casts = [
		'fecha' => 'datetime',
		'estado_enfermedad' => 'int',
		'vigencia_receta' => 'datetime',
		'diagnosticos' => 'json',
		'examenes' => 'json'
	];

	protected $fillable = [
		'hc_number',
		'form_id',
		'fecha',
		'motivo_consulta',
		'enfermedad_actual',
		'examen_fisico',
		'plan',
		'estado_enfermedad',
		'antecedente_alergico',
		'signos_alarma',
		'recomen_no_farmaco',
		'vigencia_receta',
		'diagnosticos',
		'examenes'
	];

	public function patient_datum()
	{
		return $this->belongsTo(PatientDatum::class, 'hc_number', 'hc_number');
	}

	public function consulta_examenes()
	{
		return $this->hasMany(ConsultaExamene::class, 'form_id', 'form_id');
	}
}
