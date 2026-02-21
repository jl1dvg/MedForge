<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CodeCategory
 * 
 * @property int $id
 * @property string|null $slug
 * @property string $title
 * @property bool $active
 * @property int $seq
 *
 * @package App\Models
 */
class CodeCategory extends Model
{
	protected $table = 'code_categories';
	public $timestamps = false;

	protected $casts = [
		'active' => 'bool',
		'seq' => 'int'
	];

	protected $fillable = [
		'slug',
		'title',
		'active',
		'seq'
	];
}
