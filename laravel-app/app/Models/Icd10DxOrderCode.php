<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Icd10DxOrderCode
 * 
 * @property int $dx_id
 * @property string|null $dx_code
 * @property string|null $formatted_dx_code
 * @property string|null $valid_for_coding
 * @property string|null $short_desc
 * @property string|null $long_desc
 * @property int|null $active
 * @property int|null $revision
 * 
 * @property Collection|PalabrasClave[] $palabras_claves
 *
 * @package App\Models
 */
class Icd10DxOrderCode extends Model
{
	protected $table = 'icd10_dx_order_code';
	protected $primaryKey = 'dx_id';
	public $timestamps = false;

	protected $casts = [
		'active' => 'int',
		'revision' => 'int'
	];

	protected $fillable = [
		'dx_code',
		'formatted_dx_code',
		'valid_for_coding',
		'short_desc',
		'long_desc',
		'active',
		'revision'
	];

	public function palabras_claves()
	{
		return $this->hasMany(PalabrasClave::class, 'dx_id');
	}
}
