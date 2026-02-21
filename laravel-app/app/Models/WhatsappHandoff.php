<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappHandoff
 * 
 * @property int $id
 * @property int $conversation_id
 * @property string $wa_number
 * @property string $status
 * @property string $priority
 * @property string|null $topic
 * @property int|null $handoff_role_id
 * @property int|null $assigned_agent_id
 * @property Carbon|null $assigned_at
 * @property Carbon|null $assigned_until
 * @property Carbon|null $queued_at
 * @property Carbon|null $last_activity_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class WhatsappHandoff extends Model
{
	protected $table = 'whatsapp_handoffs';

	protected $casts = [
		'conversation_id' => 'int',
		'handoff_role_id' => 'int',
		'assigned_agent_id' => 'int',
		'assigned_at' => 'datetime',
		'assigned_until' => 'datetime',
		'queued_at' => 'datetime',
		'last_activity_at' => 'datetime'
	];

	protected $fillable = [
		'conversation_id',
		'wa_number',
		'status',
		'priority',
		'topic',
		'handoff_role_id',
		'assigned_agent_id',
		'assigned_at',
		'assigned_until',
		'queued_at',
		'last_activity_at',
		'notes'
	];
}
