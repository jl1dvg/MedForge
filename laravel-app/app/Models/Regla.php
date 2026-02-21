<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Regla
 * 
 * @property int $id
 * @property string $nombre
 * @property string $tipo
 * @property string|null $descripcion
 * @property bool|null $activa
 * @property Carbon|null $creado_en
 * 
 * @property Collection|Accione[] $acciones
 * @property Collection|Condicione[] $condiciones
 *
 * @package App\Models
 */
class Regla extends Model
{
	protected $table = 'reglas';
	public $timestamps = false;

	protected $casts = [
		'activa' => 'bool',
		'creado_en' => 'datetime'
	];

	protected $fillable = [
		'nombre',
		'tipo',
		'descripcion',
		'activa',
		'creado_en'
	];

	public function acciones()
	{
		return $this->hasMany(Accione::class);
	}

	public function condiciones()
	{
		return $this->hasMany(Condicione::class);
	}
}
