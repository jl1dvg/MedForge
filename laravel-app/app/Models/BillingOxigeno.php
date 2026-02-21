<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingOxigeno
 * 
 * @property int $id
 * @property int|null $billing_id
 * @property string|null $codigo
 * @property string|null $nombre
 * @property float|null $tiempo
 * @property float|null $litros
 * @property float|null $valor1
 * @property float|null $valor2
 * @property float|null $precio
 * 
 * @property BillingMain|null $billing_main
 *
 * @package App\Models
 */
class BillingOxigeno extends Model
{
	protected $table = 'billing_oxigeno';
	public $timestamps = false;

	protected $casts = [
		'billing_id' => 'int',
		'tiempo' => 'float',
		'litros' => 'float',
		'valor1' => 'float',
		'valor2' => 'float',
		'precio' => 'float'
	];

	protected $fillable = [
		'billing_id',
		'codigo',
		'nombre',
		'tiempo',
		'litros',
		'valor1',
		'valor2',
		'precio'
	];

	public function billing_main()
	{
		return $this->belongsTo(BillingMain::class, 'billing_id');
	}
}
