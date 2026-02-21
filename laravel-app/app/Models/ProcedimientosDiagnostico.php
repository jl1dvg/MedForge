<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientosDiagnostico
 * 
 * @property int $id
 * @property string|null $procedimiento_id
 * @property string|null $nombre
 * @property string|null $definitivo
 * @property string|null $lateralidad
 * @property string|null $selector
 * 
 * @property Procedimiento|null $procedimiento
 *
 * @package App\Models
 */
class ProcedimientosDiagnostico extends Model
{
	protected $table = 'procedimientos_diagnosticos';
	public $timestamps = false;

	protected $fillable = [
		'procedimiento_id',
		'nombre',
		'definitivo',
		'lateralidad',
		'selector'
	];

	public function procedimiento()
	{
		return $this->belongsTo(Procedimiento::class);
	}
}
