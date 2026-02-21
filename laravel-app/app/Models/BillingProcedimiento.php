<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingProcedimiento
 * 
 * @property int $id
 * @property int|null $billing_id
 * @property string|null $procedimiento_id
 * @property string|null $proc_codigo
 * @property string|null $proc_detalle
 * @property float|null $proc_precio
 * 
 * @property BillingMain|null $billing_main
 *
 * @package App\Models
 */
class BillingProcedimiento extends Model
{
	protected $table = 'billing_procedimientos';
	public $timestamps = false;

	protected $casts = [
		'billing_id' => 'int',
		'proc_precio' => 'float'
	];

	protected $fillable = [
		'billing_id',
		'procedimiento_id',
		'proc_codigo',
		'proc_detalle',
		'proc_precio'
	];

	public function billing_main()
	{
		return $this->belongsTo(BillingMain::class, 'billing_id');
	}
}
