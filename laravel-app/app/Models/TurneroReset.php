<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TurneroReset
 * 
 * @property int $id
 * @property int|null $reset_by
 * @property int $solicitudes_archived
 * @property int $examenes_archived
 * @property array|null $criteria
 * @property Carbon $created_at
 * 
 * @property User|null $user
 *
 * @package App\Models
 */
class TurneroReset extends Model
{
	protected $table = 'turnero_resets';
	public $timestamps = false;

	protected $casts = [
		'reset_by' => 'int',
		'solicitudes_archived' => 'int',
		'examenes_archived' => 'int',
		'criteria' => 'json'
	];

	protected $fillable = [
		'reset_by',
		'solicitudes_archived',
		'examenes_archived',
		'criteria'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'reset_by');
	}
}
