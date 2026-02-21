<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingInsumo
 * 
 * @property int $id
 * @property int|null $billing_id
 * @property string|null $insumo_id
 * @property string|null $codigo
 * @property string|null $nombre
 * @property int|null $cantidad
 * @property float|null $precio
 * @property int $iva
 * 
 * @property BillingMain|null $billing_main
 *
 * @package App\Models
 */
class BillingInsumo extends Model
{
	protected $table = 'billing_insumos';
	public $timestamps = false;

	protected $casts = [
		'billing_id' => 'int',
		'cantidad' => 'int',
		'precio' => 'float',
		'iva' => 'int'
	];

	protected $fillable = [
		'billing_id',
		'insumo_id',
		'codigo',
		'nombre',
		'cantidad',
		'precio',
		'iva'
	];

	public function billing_main()
	{
		return $this->belongsTo(BillingMain::class, 'billing_id');
	}
}
