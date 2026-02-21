<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesSyncRun
 * 
 * @property int $id
 * @property string $job_name
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status
 * @property int $items_processed
 * @property int|null $last_cursor
 * @property string|null $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class DerivacionesSyncRun extends Model
{
	protected $table = 'derivaciones_sync_runs';

	protected $casts = [
		'started_at' => 'datetime',
		'finished_at' => 'datetime',
		'items_processed' => 'int',
		'last_cursor' => 'int'
	];

	protected $fillable = [
		'job_name',
		'started_at',
		'finished_at',
		'status',
		'items_processed',
		'last_cursor',
		'message'
	];
}
