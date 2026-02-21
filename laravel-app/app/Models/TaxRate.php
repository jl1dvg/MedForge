<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class TaxRate
 * 
 * @property int $id
 * @property string|null $rate_key
 * @property string $title
 * @property float $percent
 * @property bool $active
 * @property int $seq
 *
 * @package App\Models
 */
class TaxRate extends Model
{
	protected $table = 'tax_rates';
	public $timestamps = false;

	protected $casts = [
		'percent' => 'float',
		'active' => 'bool',
		'seq' => 'int'
	];

	protected $fillable = [
		'rate_key',
		'title',
		'percent',
		'active',
		'seq'
	];
}
