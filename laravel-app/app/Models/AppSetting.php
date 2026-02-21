<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AppSetting
 * 
 * @property int $id
 * @property string|null $category
 * @property string $name
 * @property string|null $value
 * @property string|null $type
 * @property bool $autoload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class AppSetting extends Model
{
	protected $table = 'app_settings';

	protected $casts = [
		'autoload' => 'bool'
	];

	protected $fillable = [
		'category',
		'name',
		'value',
		'type',
		'autoload'
	];
}
