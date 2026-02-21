<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTicket
 * 
 * @property int $id
 * @property string $subject
 * @property string $status
 * @property string $priority
 * @property int|null $reporter_id
 * @property int|null $assigned_to
 * @property int|null $related_lead_id
 * @property int|null $related_project_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property CrmLead|null $crm_lead
 * @property CrmProject|null $crm_project
 * @property Collection|CrmTicketMessage[] $crm_ticket_messages
 *
 * @package App\Models
 */
class CrmTicket extends Model
{
	protected $table = 'crm_tickets';

	protected $casts = [
		'reporter_id' => 'int',
		'assigned_to' => 'int',
		'related_lead_id' => 'int',
		'related_project_id' => 'int',
		'created_by' => 'int'
	];

	protected $fillable = [
		'subject',
		'status',
		'priority',
		'reporter_id',
		'assigned_to',
		'related_lead_id',
		'related_project_id',
		'created_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'reporter_id');
	}

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class, 'related_lead_id');
	}

	public function crm_project()
	{
		return $this->belongsTo(CrmProject::class, 'related_project_id');
	}

	public function crm_ticket_messages()
	{
		return $this->hasMany(CrmTicketMessage::class, 'ticket_id');
	}
}
