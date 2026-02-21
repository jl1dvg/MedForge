<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesFormId
 * 
 * @property int $id
 * @property string $cod_derivacion
 * @property string $form_id
 * @property string|null $hc_number
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_registro
 * @property Carbon|null $fecha_vigencia
 * @property string|null $referido
 * @property string|null $diagnostico
 * @property string|null $sede
 * @property string|null $parentesco
 * @property string|null $archivo_derivacion_path
 * 
 * @property Collection|IplPlanificador[] $ipl_planificadors
 *
 * @package App\Models
 */
class DerivacionesFormId extends Model
{
	protected $table = 'derivaciones_form_id';
	public $timestamps = false;

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'fecha_registro' => 'datetime',
		'fecha_vigencia' => 'datetime'
	];

	protected $fillable = [
		'cod_derivacion',
		'form_id',
		'hc_number',
		'fecha_creacion',
		'fecha_registro',
		'fecha_vigencia',
		'referido',
		'diagnostico',
		'sede',
		'parentesco',
		'archivo_derivacion_path'
	];

	public function ipl_planificadors()
	{
		return $this->hasMany(IplPlanificador::class, 'derivacion_id');
	}
}
