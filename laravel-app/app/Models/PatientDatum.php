<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PatientDatum
 * 
 * @property int $id
 * @property string|null $hc_number
 * @property Carbon|null $fecha_caducidad
 * @property string $lname
 * @property string|null $lname2
 * @property string $fname
 * @property string|null $mname
 * @property string|null $afiliacion
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $sexo
 * @property string|null $celular
 * @property string|null $ciudad
 * @property string|null $estado_civil
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $ocupacion
 * @property string|null $lugar_trabajo
 * @property string|null $parroquia
 * @property string|null $nacionalidad
 * @property string|null $id_procedencia
 * @property string|null $id_referido
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $created_by_type
 * @property string|null $created_by_identifier
 * @property string|null $updated_by_type
 * @property string|null $updated_by_identifier
 * 
 * @property Collection|ConsultaDatum[] $consulta_data
 * @property Collection|ProcedimientoProyectado[] $procedimiento_proyectados
 * @property Collection|ProtocoloDatum[] $protocolo_data
 * @property Collection|Visita[] $visitas
 *
 * @package App\Models
 */
class PatientDatum extends Model
{
	protected $table = 'patient_data';

	protected $casts = [
		'fecha_caducidad' => 'datetime',
		'fecha_nacimiento' => 'datetime'
	];

	protected $fillable = [
		'hc_number',
		'fecha_caducidad',
		'lname',
		'lname2',
		'fname',
		'mname',
		'afiliacion',
		'fecha_nacimiento',
		'sexo',
		'celular',
		'ciudad',
		'estado_civil',
		'email',
		'direccion',
		'ocupacion',
		'lugar_trabajo',
		'parroquia',
		'nacionalidad',
		'id_procedencia',
		'id_referido',
		'created_by_type',
		'created_by_identifier',
		'updated_by_type',
		'updated_by_identifier'
	];

	public function consulta_data()
	{
		return $this->hasMany(ConsultaDatum::class, 'hc_number', 'hc_number');
	}

	public function procedimiento_proyectados()
	{
		return $this->hasMany(ProcedimientoProyectado::class, 'hc_number', 'hc_number');
	}

	public function protocolo_data()
	{
		return $this->hasMany(ProtocoloDatum::class, 'hc_number', 'hc_number');
	}

	public function visitas()
	{
		return $this->hasMany(Visita::class, 'hc_number', 'hc_number');
	}
}
