<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PrefacturaPaciente
 * 
 * @property int $id
 * @property string|null $sede
 * @property string|null $area
 * @property string|null $afiliacion
 * @property string|null $parentesco
 * @property string|null $hc_number
 * @property string|null $procedencia
 * @property string|null $referido
 * @property string|null $tipo_afiliacion
 * @property string|null $numero_aprobacion
 * @property string|null $tipo_plan
 * @property Carbon|null $fecha_registro
 * @property Carbon|null $fecha_vigencia
 * @property string|null $cod_derivacion
 * @property string|null $num_secuencial_derivacion
 * @property string|null $num_historia
 * @property string|null $examen_fisico
 * @property array|null $procedimientos
 * @property array|null $diagnosticos
 * @property string|null $enfermedad_catastrofica
 * @property string|null $discapacidad
 * @property bool|null $jefe_hogar
 * @property string|null $observaciones
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_actualizacion
 * 
 * @property Collection|PrefacturaDetalleDiagnostico[] $prefactura_detalle_diagnosticos
 * @property Collection|PrefacturaDetalleProcedimiento[] $prefactura_detalle_procedimientos
 * @property Collection|PrefacturaPayloadAudit[] $prefactura_payload_audits
 *
 * @package App\Models
 */
class PrefacturaPaciente extends Model
{
	protected $table = 'prefactura_paciente';
	public $timestamps = false;

	protected $casts = [
		'fecha_registro' => 'datetime',
		'fecha_vigencia' => 'datetime',
		'procedimientos' => 'json',
		'diagnosticos' => 'json',
		'jefe_hogar' => 'bool',
		'fecha_creacion' => 'datetime',
		'fecha_actualizacion' => 'datetime'
	];

	protected $fillable = [
		'sede',
		'area',
		'afiliacion',
		'parentesco',
		'hc_number',
		'procedencia',
		'referido',
		'tipo_afiliacion',
		'numero_aprobacion',
		'tipo_plan',
		'fecha_registro',
		'fecha_vigencia',
		'cod_derivacion',
		'num_secuencial_derivacion',
		'num_historia',
		'examen_fisico',
		'procedimientos',
		'diagnosticos',
		'enfermedad_catastrofica',
		'discapacidad',
		'jefe_hogar',
		'observaciones',
		'fecha_creacion',
		'fecha_actualizacion'
	];

	public function prefactura_detalle_diagnosticos()
	{
		return $this->hasMany(PrefacturaDetalleDiagnostico::class, 'prefactura_id');
	}

	public function prefactura_detalle_procedimientos()
	{
		return $this->hasMany(PrefacturaDetalleProcedimiento::class, 'prefactura_id');
	}

	public function prefactura_payload_audits()
	{
		return $this->hasMany(PrefacturaPayloadAudit::class, 'prefactura_id');
	}
}
