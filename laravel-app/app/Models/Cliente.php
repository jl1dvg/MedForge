<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Cliente
 * 
 * @property int $id
 * @property string|null $nombre
 * @property string|null $token_acceso
 * 
 * @property Collection|ServiciosCliente[] $servicios_clientes
 *
 * @package App\Models
 */
class Cliente extends Model
{
	protected $table = 'clientes';
	public $timestamps = false;

	protected $fillable = [
		'nombre',
		'token_acceso'
	];

	public function servicios_clientes()
	{
		return $this->hasMany(ServiciosCliente::class);
	}
}
