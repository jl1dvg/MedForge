<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmProject
 * 
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property int|null $owner_id
 * @property int|null $lead_id
 * @property int|null $customer_id
 * @property string|null $hc_number
 * @property int|null $form_id
 * @property string|null $source_module
 * @property string|null $source_ref_id
 * @property string|null $episode_type
 * @property string|null $eye
 * @property Carbon|null $start_date
 * @property Carbon|null $due_date
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property CrmCustomer|null $crm_customer
 * @property CrmLead|null $crm_lead
 * @property Collection|CrmTask[] $crm_tasks
 * @property Collection|CrmTicket[] $crm_tickets
 * @property Collection|SolicitudCrmDetalle[] $solicitud_crm_detalles
 *
 * @package App\Models
 */
class CrmProject extends Model
{
	protected $table = 'crm_projects';

	protected $casts = [
		'owner_id' => 'int',
		'lead_id' => 'int',
		'customer_id' => 'int',
		'form_id' => 'int',
		'start_date' => 'datetime',
		'due_date' => 'datetime',
		'created_by' => 'int'
	];

	protected $fillable = [
		'title',
		'description',
		'status',
		'owner_id',
		'lead_id',
		'customer_id',
		'hc_number',
		'form_id',
		'source_module',
		'source_ref_id',
		'episode_type',
		'eye',
		'start_date',
		'due_date',
		'created_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'owner_id');
	}

	public function crm_customer()
	{
		return $this->belongsTo(CrmCustomer::class, 'customer_id');
	}

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class, 'lead_id');
	}

	public function crm_tasks()
	{
		return $this->hasMany(CrmTask::class, 'project_id');
	}

	public function crm_tickets()
	{
		return $this->hasMany(CrmTicket::class, 'related_project_id');
	}

	public function solicitud_crm_detalles()
	{
		return $this->hasMany(SolicitudCrmDetalle::class);
	}
}
