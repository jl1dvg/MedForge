<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingMain
 * 
 * @property int $id
 * @property string|null $hc_number
 * @property string|null $form_id
 * @property int|null $facturado_por
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|BillingAnestesium[] $billing_anestesia
 * @property Collection|BillingDerecho[] $billing_derechos
 * @property Collection|BillingInsumo[] $billing_insumos
 * @property Collection|BillingOxigeno[] $billing_oxigenos
 * @property Collection|BillingProcedimiento[] $billing_procedimientos
 * @property Collection|BillingSriDocument[] $billing_sri_documents
 *
 * @package App\Models
 */
class BillingMain extends Model
{
	protected $table = 'billing_main';

	protected $casts = [
		'facturado_por' => 'int'
	];

	protected $fillable = [
		'hc_number',
		'form_id',
		'facturado_por'
	];

	public function billing_anestesia()
	{
		return $this->hasMany(BillingAnestesium::class, 'billing_id');
	}

	public function billing_derechos()
	{
		return $this->hasMany(BillingDerecho::class, 'billing_id');
	}

	public function billing_insumos()
	{
		return $this->hasMany(BillingInsumo::class, 'billing_id');
	}

	public function billing_oxigenos()
	{
		return $this->hasMany(BillingOxigeno::class, 'billing_id');
	}

	public function billing_procedimientos()
	{
		return $this->hasMany(BillingProcedimiento::class, 'billing_id');
	}

	public function billing_sri_documents()
	{
		return $this->hasMany(BillingSriDocument::class, 'billing_id');
	}
}
