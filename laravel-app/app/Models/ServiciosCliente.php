<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ServiciosCliente
 * 
 * @property int $id
 * @property int|null $cliente_id
 * @property string|null $servicio_nombre
 * @property bool|null $activo
 * 
 * @property Cliente|null $cliente
 *
 * @package App\Models
 */
class ServiciosCliente extends Model
{
	protected $table = 'servicios_cliente';
	public $timestamps = false;

	protected $casts = [
		'cliente_id' => 'int',
		'activo' => 'bool'
	];

	protected $fillable = [
		'cliente_id',
		'servicio_nombre',
		'activo'
	];

	public function cliente()
	{
		return $this->belongsTo(Cliente::class);
	}
}
