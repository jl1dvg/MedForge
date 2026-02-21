<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTaskReminder
 * 
 * @property int $id
 * @property int $task_id
 * @property int $company_id
 * @property Carbon $remind_at
 * @property string $channel
 * @property Carbon|null $created_at
 * 
 * @property CrmTask $crm_task
 *
 * @package App\Models
 */
class CrmTaskReminder extends Model
{
	protected $table = 'crm_task_reminders';
	public $timestamps = false;

	protected $casts = [
		'task_id' => 'int',
		'company_id' => 'int',
		'remind_at' => 'datetime'
	];

	protected $fillable = [
		'task_id',
		'company_id',
		'remind_at',
		'channel'
	];

	public function crm_task()
	{
		return $this->belongsTo(CrmTask::class, 'task_id');
	}
}
