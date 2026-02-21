<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Accione
 * 
 * @property int $id
 * @property int $regla_id
 * @property string $tipo
 * @property string $parametro
 * 
 * @property Regla $regla
 *
 * @package App\Models
 */
class Accione extends Model
{
	protected $table = 'acciones';
	public $timestamps = false;

	protected $casts = [
		'regla_id' => 'int'
	];

	protected $fillable = [
		'regla_id',
		'tipo',
		'parametro'
	];

	public function regla()
	{
		return $this->belongsTo(Regla::class);
	}
}
