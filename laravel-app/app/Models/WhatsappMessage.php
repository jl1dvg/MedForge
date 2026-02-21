<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappMessage
 * 
 * @property int $id
 * @property int $conversation_id
 * @property string|null $wa_message_id
 * @property string $direction
 * @property string $message_type
 * @property string|null $body
 * @property array|null $raw_payload
 * @property string|null $status
 * @property Carbon|null $message_timestamp
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappConversation $whatsapp_conversation
 *
 * @package App\Models
 */
class WhatsappMessage extends Model
{
	protected $table = 'whatsapp_messages';

	protected $casts = [
		'conversation_id' => 'int',
		'raw_payload' => 'json',
		'message_timestamp' => 'datetime',
		'sent_at' => 'datetime',
		'delivered_at' => 'datetime',
		'read_at' => 'datetime'
	];

	protected $fillable = [
		'conversation_id',
		'wa_message_id',
		'direction',
		'message_type',
		'body',
		'raw_payload',
		'status',
		'message_timestamp',
		'sent_at',
		'delivered_at',
		'read_at'
	];

	public function whatsapp_conversation()
	{
		return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
	}
}
