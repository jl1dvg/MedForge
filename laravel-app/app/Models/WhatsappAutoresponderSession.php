<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderSession
 * 
 * @property int $id
 * @property int $conversation_id
 * @property string $wa_number
 * @property string|null $scenario_id
 * @property string|null $node_id
 * @property string|null $awaiting
 * @property array|null $context
 * @property array|null $last_payload
 * @property Carbon $last_interaction_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappConversation $whatsapp_conversation
 *
 * @package App\Models
 */
class WhatsappAutoresponderSession extends Model
{
	protected $table = 'whatsapp_autoresponder_sessions';

	protected $casts = [
		'conversation_id' => 'int',
		'context' => 'json',
		'last_payload' => 'json',
		'last_interaction_at' => 'datetime'
	];

	protected $fillable = [
		'conversation_id',
		'wa_number',
		'scenario_id',
		'node_id',
		'awaiting',
		'context',
		'last_payload',
		'last_interaction_at'
	];

	public function whatsapp_conversation()
	{
		return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
	}
}
