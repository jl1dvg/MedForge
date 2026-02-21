<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CodeTaxRate
 * 
 * @property int $id
 * @property int $code_id
 * @property string $rate_key
 * 
 * @property Tarifario2014 $tarifario2014
 *
 * @package App\Models
 */
class CodeTaxRate extends Model
{
	protected $table = 'code_tax_rate';
	public $timestamps = false;

	protected $casts = [
		'code_id' => 'int'
	];

	protected $fillable = [
		'code_id',
		'rate_key'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'code_id');
	}
}
