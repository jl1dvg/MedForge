<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappHandoffEvent
 * 
 * @property int $id
 * @property int $handoff_id
 * @property string $event_type
 * @property int|null $actor_user_id
 * @property string|null $notes
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class WhatsappHandoffEvent extends Model
{
	protected $table = 'whatsapp_handoff_events';
	public $timestamps = false;

	protected $casts = [
		'handoff_id' => 'int',
		'actor_user_id' => 'int'
	];

	protected $fillable = [
		'handoff_id',
		'event_type',
		'actor_user_id',
		'notes'
	];
}
