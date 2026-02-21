<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappInboxMessage
 * 
 * @property int $id
 * @property string $wa_number
 * @property string $direction
 * @property string $message_type
 * @property string $message_body
 * @property string|null $message_id
 * @property string|null $payload
 * @property Carbon|null $created_at
 *
 * @package App\Models
 */
class WhatsappInboxMessage extends Model
{
	protected $table = 'whatsapp_inbox_messages';
	public $timestamps = false;

	protected $fillable = [
		'wa_number',
		'direction',
		'message_type',
		'message_body',
		'message_id',
		'payload'
	];
}
