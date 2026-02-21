<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTicketMessage
 * 
 * @property int $id
 * @property int $ticket_id
 * @property int|null $author_id
 * @property string $message
 * @property Carbon|null $created_at
 * 
 * @property User|null $user
 * @property CrmTicket $crm_ticket
 *
 * @package App\Models
 */
class CrmTicketMessage extends Model
{
	protected $table = 'crm_ticket_messages';
	public $timestamps = false;

	protected $casts = [
		'ticket_id' => 'int',
		'author_id' => 'int'
	];

	protected $fillable = [
		'ticket_id',
		'author_id',
		'message'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'author_id');
	}

	public function crm_ticket()
	{
		return $this->belongsTo(CrmTicket::class, 'ticket_id');
	}
}
