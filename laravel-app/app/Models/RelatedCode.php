<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class RelatedCode
 * 
 * @property int $id
 * @property int $code_id
 * @property int $related_code_id
 * @property string|null $relation_type
 * 
 * @property Tarifario2014 $tarifario2014
 *
 * @package App\Models
 */
class RelatedCode extends Model
{
	protected $table = 'related_codes';
	public $timestamps = false;

	protected $casts = [
		'code_id' => 'int',
		'related_code_id' => 'int'
	];

	protected $fillable = [
		'code_id',
		'related_code_id',
		'relation_type'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'related_code_id');
	}
}
