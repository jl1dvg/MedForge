<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class DiagnosticosAsignado
 * 
 * @property int $id
 * @property int $form_id
 * @property string $fuente
 * @property string|null $dx_code
 * @property string|null $descripcion
 * @property bool|null $definitivo
 * @property string|null $lateralidad
 * @property string|null $selector
 * 
 * @property ProcedimientoProyectado $procedimiento_proyectado
 *
 * @package App\Models
 */
class DiagnosticosAsignado extends Model
{
	protected $table = 'diagnosticos_asignados';
	public $timestamps = false;

	protected $casts = [
		'form_id' => 'int',
		'definitivo' => 'bool'
	];

	protected $fillable = [
		'form_id',
		'fuente',
		'dx_code',
		'descripcion',
		'definitivo',
		'lateralidad',
		'selector'
	];

	public function procedimiento_proyectado()
	{
		return $this->belongsTo(ProcedimientoProyectado::class, 'form_id', 'form_id');
	}
}
