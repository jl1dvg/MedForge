<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientosTecnico
 * 
 * @property int $id
 * @property string|null $procedimiento_id
 * @property string|null $funcion
 * @property string|null $trabajador
 * @property string|null $nombre
 * @property string|null $selector
 * 
 * @property Procedimiento|null $procedimiento
 *
 * @package App\Models
 */
class ProcedimientosTecnico extends Model
{
	protected $table = 'procedimientos_tecnicos';
	public $timestamps = false;

	protected $fillable = [
		'procedimiento_id',
		'funcion',
		'trabajador',
		'nombre',
		'selector'
	];

	public function procedimiento()
	{
		return $this->belongsTo(Procedimiento::class);
	}
}
