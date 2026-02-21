<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Procedimiento
 * 
 * @property string $id
 * @property string $cirugia
 * @property string $categoria
 * @property string $membrete
 * @property int|null $medicacion
 * @property int|null $cardex
 * @property string|null $dieresis
 * @property string|null $exposicion
 * @property string|null $hallazgo
 * @property string|null $operatorio
 * @property string $anestesia
 * @property string|null $complicacionesoperatorio
 * @property string|null $perdidasanguineat
 * @property int|null $staffCount
 * @property int|null $codigoCount
 * @property int|null $diagnosticoCount
 * @property string|null $horas
 * @property string $dx_pre
 * @property string $dx_post
 * @property string|null $imagen_link
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_actualizacion
 * 
 * @property Evolucion005|null $evolucion005
 * @property Kardex|null $kardex
 * @property Collection|ProcedimientosCodigo[] $procedimientos_codigos
 * @property Collection|ProcedimientosDiagnostico[] $procedimientos_diagnosticos
 * @property Collection|ProcedimientosTecnico[] $procedimientos_tecnicos
 *
 * @package App\Models
 */
class Procedimiento extends Model
{
	protected $table = 'procedimientos';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'medicacion' => 'int',
		'cardex' => 'int',
		'staffCount' => 'int',
		'codigoCount' => 'int',
		'diagnosticoCount' => 'int',
		'fecha_creacion' => 'datetime',
		'fecha_actualizacion' => 'datetime'
	];

	protected $fillable = [
		'cirugia',
		'categoria',
		'membrete',
		'medicacion',
		'cardex',
		'dieresis',
		'exposicion',
		'hallazgo',
		'operatorio',
		'anestesia',
		'complicacionesoperatorio',
		'perdidasanguineat',
		'staffCount',
		'codigoCount',
		'diagnosticoCount',
		'horas',
		'dx_pre',
		'dx_post',
		'imagen_link',
		'fecha_creacion',
		'fecha_actualizacion'
	];

	public function evolucion005()
	{
		return $this->hasOne(Evolucion005::class, 'id');
	}

	public function kardex()
	{
		return $this->hasOne(Kardex::class);
	}

	public function procedimientos_codigos()
	{
		return $this->hasMany(ProcedimientosCodigo::class);
	}

	public function procedimientos_diagnosticos()
	{
		return $this->hasMany(ProcedimientosDiagnostico::class);
	}

	public function procedimientos_tecnicos()
	{
		return $this->hasMany(ProcedimientosTecnico::class);
	}
}
