<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PriceLevel
 * 
 * @property int $id
 * @property string|null $level_key
 * @property string $title
 * @property int $seq
 * @property bool $active
 *
 * @package App\Models
 */
class PriceLevel extends Model
{
	protected $table = 'price_levels';
	public $timestamps = false;

	protected $casts = [
		'seq' => 'int',
		'active' => 'bool'
	];

	protected $fillable = [
		'level_key',
		'title',
		'seq',
		'active'
	];
}
