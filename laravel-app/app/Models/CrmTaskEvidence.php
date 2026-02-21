<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTaskEvidence
 * 
 * @property int $id
 * @property int $task_id
 * @property string $evidence_type
 * @property string|null $payload
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * 
 * @property CrmTask $crm_task
 * @property User|null $user
 *
 * @package App\Models
 */
class CrmTaskEvidence extends Model
{
	protected $table = 'crm_task_evidence';
	public $timestamps = false;

	protected $casts = [
		'task_id' => 'int',
		'created_by' => 'int'
	];

	protected $fillable = [
		'task_id',
		'evidence_type',
		'payload',
		'created_by'
	];

	public function crm_task()
	{
		return $this->belongsTo(CrmTask::class, 'task_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}
}
