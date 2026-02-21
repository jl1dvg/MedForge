<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MedforgeCronTask
 * 
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $schedule_interval
 * @property bool $is_active
 * @property Carbon|null $last_run_at
 * @property Carbon|null $next_run_at
 * @property string|null $last_status
 * @property string|null $last_message
 * @property string|null $last_output
 * @property string|null $last_error
 * @property int|null $last_duration_ms
 * @property int $failure_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $settings
 * 
 * @property Collection|MedforgeCronLog[] $medforge_cron_logs
 *
 * @package App\Models
 */
class MedforgeCronTask extends Model
{
	protected $table = 'medforge_cron_tasks';

	protected $casts = [
		'schedule_interval' => 'int',
		'is_active' => 'bool',
		'last_run_at' => 'datetime',
		'next_run_at' => 'datetime',
		'last_duration_ms' => 'int',
		'failure_count' => 'int'
	];

	protected $fillable = [
		'slug',
		'name',
		'description',
		'schedule_interval',
		'is_active',
		'last_run_at',
		'next_run_at',
		'last_status',
		'last_message',
		'last_output',
		'last_error',
		'last_duration_ms',
		'failure_count',
		'settings'
	];

	public function medforge_cron_logs()
	{
		return $this->hasMany(MedforgeCronLog::class, 'task_id');
	}
}
