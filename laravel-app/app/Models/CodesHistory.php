<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CodesHistory
 * 
 * @property int $id
 * @property Carbon $action_at
 * @property string $action_type
 * @property string $user
 * @property int|null $code_id
 * @property array $snapshot
 *
 * @package App\Models
 */
class CodesHistory extends Model
{
	protected $table = 'codes_history';
	public $timestamps = false;

	protected $casts = [
		'action_at' => 'datetime',
		'code_id' => 'int',
		'snapshot' => 'json'
	];

	protected $fillable = [
		'action_at',
		'action_type',
		'user',
		'code_id',
		'snapshot'
	];
}
