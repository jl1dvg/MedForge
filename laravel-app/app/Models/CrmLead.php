<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmLead
 * 
 * @property int $id
 * @property string|null $hc_number
 * @property int|null $customer_id
 * @property string $name
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property string|null $last_stage_notified
 * @property Carbon|null $last_stage_notified_at
 * @property string|null $source
 * @property string|null $notes
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property CrmCustomer|null $crm_customer
 * @property Collection|CrmLeadLink[] $crm_lead_links
 * @property Collection|CrmProject[] $crm_projects
 * @property Collection|CrmProposal[] $crm_proposals
 * @property Collection|CrmTicket[] $crm_tickets
 * @property Collection|ExamenCrmDetalle[] $examen_crm_detalles
 * @property Collection|SolicitudCrmDetalle[] $solicitud_crm_detalles
 *
 * @package App\Models
 */
class CrmLead extends Model
{
	protected $table = 'crm_leads';

	protected $casts = [
		'customer_id' => 'int',
		'last_stage_notified_at' => 'datetime',
		'assigned_to' => 'int',
		'created_by' => 'int'
	];

	protected $fillable = [
		'hc_number',
		'customer_id',
		'name',
		'first_name',
		'last_name',
		'email',
		'phone',
		'status',
		'last_stage_notified',
		'last_stage_notified_at',
		'source',
		'notes',
		'assigned_to',
		'created_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function crm_customer()
	{
		return $this->belongsTo(CrmCustomer::class, 'customer_id');
	}

	public function crm_lead_links()
	{
		return $this->hasMany(CrmLeadLink::class, 'lead_id');
	}

	public function crm_projects()
	{
		return $this->hasMany(CrmProject::class, 'lead_id');
	}

	public function crm_proposals()
	{
		return $this->hasMany(CrmProposal::class, 'lead_id');
	}

	public function crm_tickets()
	{
		return $this->hasMany(CrmTicket::class, 'related_lead_id');
	}

	public function examen_crm_detalles()
	{
		return $this->hasMany(ExamenCrmDetalle::class);
	}

	public function solicitud_crm_detalles()
	{
		return $this->hasMany(SolicitudCrmDetalle::class);
	}
}
