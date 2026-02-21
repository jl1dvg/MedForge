<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ProtocoloInsumo
 * 
 * @property int $id
 * @property int $protocolo_id
 * @property int|null $insumo_id
 * @property string|null $nombre
 * @property int|null $cantidad
 * @property string|null $categoria
 * 
 * @property ProtocoloDatum $protocolo_datum
 *
 * @package App\Models
 */
class ProtocoloInsumo extends Model
{
	protected $table = 'protocolo_insumos';
	public $timestamps = false;

	protected $casts = [
		'protocolo_id' => 'int',
		'insumo_id' => 'int',
		'cantidad' => 'int'
	];

	protected $fillable = [
		'protocolo_id',
		'insumo_id',
		'nombre',
		'cantidad',
		'categoria'
	];

	public function protocolo_datum()
	{
		return $this->belongsTo(ProtocoloDatum::class, 'protocolo_id');
	}
}
