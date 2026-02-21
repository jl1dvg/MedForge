<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Insumo
 * 
 * @property int $id
 * @property string $nombre
 * @property string $categoria
 * @property string|null $codigo_isspol
 * @property string|null $codigo_issfa
 * @property string|null $codigo_iess
 * @property string|null $codigo_msp
 * @property string|null $producto_issfa
 * @property float|null $precio_base
 * @property float|null $iva_15
 * @property float|null $gestion_10
 * @property float|null $precio_total
 * @property float|null $precio_isspol
 * @property float|null $precio_issfa
 * @property float|null $precio_iess
 * @property float|null $precio_msp
 * @property bool|null $es_medicamento
 *
 * @package App\Models
 */
class Insumo extends Model
{
	protected $table = 'insumos';
	public $timestamps = false;

	protected $casts = [
		'precio_base' => 'float',
		'iva_15' => 'float',
		'gestion_10' => 'float',
		'precio_total' => 'float',
		'precio_isspol' => 'float',
		'precio_issfa' => 'float',
		'precio_iess' => 'float',
		'precio_msp' => 'float',
		'es_medicamento' => 'bool'
	];

	protected $fillable = [
		'nombre',
		'categoria',
		'codigo_isspol',
		'codigo_issfa',
		'codigo_iess',
		'codigo_msp',
		'producto_issfa',
		'precio_base',
		'iva_15',
		'gestion_10',
		'precio_total',
		'precio_isspol',
		'precio_issfa',
		'precio_iess',
		'precio_msp',
		'es_medicamento'
	];
}
