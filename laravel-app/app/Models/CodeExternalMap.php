<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CodeExternalMap
 * 
 * @property int $id
 * @property int $code_id
 * @property int $external_code_id
 * @property string|null $relation_type
 * 
 * @property Tarifario2014 $tarifario2014
 * @property ExternalCode $external_code
 *
 * @package App\Models
 */
class CodeExternalMap extends Model
{
	protected $table = 'code_external_map';
	public $timestamps = false;

	protected $casts = [
		'code_id' => 'int',
		'external_code_id' => 'int'
	];

	protected $fillable = [
		'code_id',
		'external_code_id',
		'relation_type'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'code_id');
	}

	public function external_code()
	{
		return $this->belongsTo(ExternalCode::class);
	}
}
