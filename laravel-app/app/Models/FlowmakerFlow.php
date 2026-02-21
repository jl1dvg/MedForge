<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class FlowmakerFlow
 * 
 * @property int $id
 * @property string|null $flow_key
 * @property string $name
 * @property string|null $description
 * @property string|null $flow_data
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property User|null $user
 *
 * @package App\Models
 */
class FlowmakerFlow extends Model
{
	protected $table = 'flowmaker_flows';

	protected $casts = [
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'flow_key',
		'name',
		'description',
		'flow_data',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}
}
