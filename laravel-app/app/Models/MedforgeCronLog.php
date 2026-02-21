<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MedforgeCronLog
 * 
 * @property int $id
 * @property int $task_id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status
 * @property string|null $message
 * @property string|null $output
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon $created_at
 * 
 * @property MedforgeCronTask $medforge_cron_task
 *
 * @package App\Models
 */
class MedforgeCronLog extends Model
{
	protected $table = 'medforge_cron_logs';
	public $timestamps = false;

	protected $casts = [
		'task_id' => 'int',
		'started_at' => 'datetime',
		'finished_at' => 'datetime',
		'duration_ms' => 'int'
	];

	protected $fillable = [
		'task_id',
		'started_at',
		'finished_at',
		'status',
		'message',
		'output',
		'error',
		'duration_ms'
	];

	public function medforge_cron_task()
	{
		return $this->belongsTo(MedforgeCronTask::class, 'task_id');
	}
}
