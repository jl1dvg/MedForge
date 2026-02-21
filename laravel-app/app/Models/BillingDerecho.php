<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingDerecho
 * 
 * @property int $id
 * @property int|null $billing_id
 * @property string|null $derecho_id
 * @property string|null $codigo
 * @property string|null $detalle
 * @property int|null $cantidad
 * @property float|null $iva
 * @property float|null $precio_afiliacion
 * 
 * @property BillingMain|null $billing_main
 *
 * @package App\Models
 */
class BillingDerecho extends Model
{
	protected $table = 'billing_derechos';
	public $timestamps = false;

	protected $casts = [
		'billing_id' => 'int',
		'cantidad' => 'int',
		'iva' => 'float',
		'precio_afiliacion' => 'float'
	];

	protected $fillable = [
		'billing_id',
		'derecho_id',
		'codigo',
		'detalle',
		'cantidad',
		'iva',
		'precio_afiliacion'
	];

	public function billing_main()
	{
		return $this->belongsTo(BillingMain::class, 'billing_id');
	}
}
