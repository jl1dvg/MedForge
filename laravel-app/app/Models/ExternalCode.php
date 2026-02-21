<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExternalCode
 * 
 * @property int $id
 * @property string $code
 * @property string $code_type
 * @property string $text
 * 
 * @property Collection|CodeExternalMap[] $code_external_maps
 *
 * @package App\Models
 */
class ExternalCode extends Model
{
	protected $table = 'external_codes';
	public $timestamps = false;

	protected $fillable = [
		'code',
		'code_type',
		'text'
	];

	public function code_external_maps()
	{
		return $this->hasMany(CodeExternalMap::class);
	}
}
