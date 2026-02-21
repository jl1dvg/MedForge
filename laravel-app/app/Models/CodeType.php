<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CodeType
 * 
 * @property int $id
 * @property string|null $key_name
 * @property string $label
 * @property string|null $mask
 * @property bool $external
 * @property string|null $rel
 * @property bool $fee
 *
 * @package App\Models
 */
class CodeType extends Model
{
	protected $table = 'code_types';
	public $timestamps = false;

	protected $casts = [
		'external' => 'bool',
		'fee' => 'bool'
	];

	protected $fillable = [
		'key_name',
		'label',
		'mask',
		'external',
		'rel',
		'fee'
	];
}
