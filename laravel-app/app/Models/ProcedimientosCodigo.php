<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcedimientosCodigo
 * 
 * @property int $id
 * @property string|null $procedimiento_id
 * @property string|null $nombre
 * @property string|null $lateralidad
 * @property string|null $selector
 * 
 * @property Procedimiento|null $procedimiento
 *
 * @package App\Models
 */
class ProcedimientosCodigo extends Model
{
	protected $table = 'procedimientos_codigos';
	public $timestamps = false;

	protected $fillable = [
		'procedimiento_id',
		'nombre',
		'lateralidad',
		'selector'
	];

	public function procedimiento()
	{
		return $this->belongsTo(Procedimiento::class);
	}
}
