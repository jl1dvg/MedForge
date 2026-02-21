<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Condicione
 * 
 * @property int $id
 * @property int $regla_id
 * @property string $campo
 * @property string $operador
 * @property string $valor
 * 
 * @property Regla $regla
 *
 * @package App\Models
 */
class Condicione extends Model
{
	protected $table = 'condiciones';
	public $timestamps = false;

	protected $casts = [
		'regla_id' => 'int'
	];

	protected $fillable = [
		'regla_id',
		'campo',
		'operador',
		'valor'
	];

	public function regla()
	{
		return $this->belongsTo(Regla::class);
	}
}
