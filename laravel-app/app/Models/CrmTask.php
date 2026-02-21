<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTask
 * 
 * @property int $id
 * @property int $company_id
 * @property int|null $project_id
 * @property string|null $entity_type
 * @property string|null $entity_id
 * @property int|null $lead_id
 * @property int|null $customer_id
 * @property string|null $hc_number
 * @property string|null $patient_id
 * @property int|null $form_id
 * @property string|null $source_module
 * @property string|null $source_ref_id
 * @property string|null $episode_type
 * @property string|null $eye
 * @property string $title
 * @property string|null $description
 * @property string $status
 * @property string $priority
 * @property string|null $category
 * @property array|null $tags
 * @property array|null $metadata
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property Carbon|null $due_date
 * @property Carbon|null $due_at
 * @property Carbon|null $remind_at
 * @property string $remind_channel
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property CrmProject|null $crm_project
 * @property Collection|CrmTaskEvidence[] $crm_task_evidences
 * @property Collection|CrmTaskReminder[] $crm_task_reminders
 *
 * @package App\Models
 */
class CrmTask extends Model
{
	protected $table = 'crm_tasks';

	protected $casts = [
		'company_id' => 'int',
		'project_id' => 'int',
		'lead_id' => 'int',
		'customer_id' => 'int',
		'form_id' => 'int',
		'tags' => 'json',
		'metadata' => 'json',
		'assigned_to' => 'int',
		'created_by' => 'int',
		'due_date' => 'datetime',
		'due_at' => 'datetime',
		'remind_at' => 'datetime',
		'completed_at' => 'datetime'
	];

	protected $fillable = [
		'company_id',
		'project_id',
		'entity_type',
		'entity_id',
		'lead_id',
		'customer_id',
		'hc_number',
		'patient_id',
		'form_id',
		'source_module',
		'source_ref_id',
		'episode_type',
		'eye',
		'title',
		'description',
		'status',
		'priority',
		'category',
		'tags',
		'metadata',
		'assigned_to',
		'created_by',
		'due_date',
		'due_at',
		'remind_at',
		'remind_channel',
		'completed_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function crm_project()
	{
		return $this->belongsTo(CrmProject::class, 'project_id');
	}

	public function crm_task_evidences()
	{
		return $this->hasMany(CrmTaskEvidence::class, 'task_id');
	}

	public function crm_task_reminders()
	{
		return $this->hasMany(CrmTaskReminder::class, 'task_id');
	}
}
