<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappConversation
 * 
 * @property int $id
 * @property string $wa_number
 * @property string|null $display_name
 * @property string|null $patient_hc_number
 * @property string|null $patient_full_name
 * @property Carbon|null $last_message_at
 * @property string|null $last_message_direction
 * @property string|null $last_message_type
 * @property string|null $last_message_preview
 * @property bool $needs_human
 * @property string|null $handoff_notes
 * @property int|null $handoff_role_id
 * @property int|null $assigned_user_id
 * @property Carbon|null $assigned_at
 * @property Carbon|null $handoff_requested_at
 * @property int $unread_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderSession|null $whatsapp_autoresponder_session
 * @property Collection|WhatsappMessage[] $whatsapp_messages
 *
 * @package App\Models
 */
class WhatsappConversation extends Model
{
	protected $table = 'whatsapp_conversations';

	protected $casts = [
		'last_message_at' => 'datetime',
		'needs_human' => 'bool',
		'handoff_role_id' => 'int',
		'assigned_user_id' => 'int',
		'assigned_at' => 'datetime',
		'handoff_requested_at' => 'datetime',
		'unread_count' => 'int'
	];

	protected $fillable = [
		'wa_number',
		'display_name',
		'patient_hc_number',
		'patient_full_name',
		'last_message_at',
		'last_message_direction',
		'last_message_type',
		'last_message_preview',
		'needs_human',
		'handoff_notes',
		'handoff_role_id',
		'assigned_user_id',
		'assigned_at',
		'handoff_requested_at',
		'unread_count'
	];

	public function whatsapp_autoresponder_session()
	{
		return $this->hasOne(WhatsappAutoresponderSession::class, 'conversation_id');
	}

	public function whatsapp_messages()
	{
		return $this->hasMany(WhatsappMessage::class, 'conversation_id');
	}
}
