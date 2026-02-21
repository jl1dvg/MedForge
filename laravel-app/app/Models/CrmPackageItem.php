<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmPackageItem
 * 
 * @property int $id
 * @property int $package_id
 * @property int|null $code_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property float $discount_percent
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Tarifario2014|null $tarifario2014
 * @property CrmPackage $crm_package
 *
 * @package App\Models
 */
class CrmPackageItem extends Model
{
	protected $table = 'crm_package_items';

	protected $casts = [
		'package_id' => 'int',
		'code_id' => 'int',
		'quantity' => 'float',
		'unit_price' => 'float',
		'discount_percent' => 'float',
		'sort_order' => 'int',
		'metadata' => 'json'
	];

	protected $fillable = [
		'package_id',
		'code_id',
		'description',
		'quantity',
		'unit_price',
		'discount_percent',
		'sort_order',
		'metadata'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'code_id');
	}

	public function crm_package()
	{
		return $this->belongsTo(CrmPackage::class, 'package_id');
	}
}
