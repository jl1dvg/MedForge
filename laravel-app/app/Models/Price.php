<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Price
 * 
 * @property int $id
 * @property int $code_id
 * @property string $level_key
 * @property float $price
 * 
 * @property Tarifario2014 $tarifario2014
 *
 * @package App\Models
 */
class Price extends Model
{
	protected $table = 'prices';
	public $timestamps = false;

	protected $casts = [
		'code_id' => 'int',
		'price' => 'float'
	];

	protected $fillable = [
		'code_id',
		'level_key',
		'price'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'code_id');
	}
}
