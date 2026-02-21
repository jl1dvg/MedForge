<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MedforgeReportQueue
 * 
 * @property int $id
 * @property string $report_type
 * @property array $payload_json
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $file_path
 * @property string|null $error_message
 * @property Carbon $available_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class MedforgeReportQueue extends Model
{
	protected $table = 'medforge_report_queue';

	protected $casts = [
		'payload_json' => 'json',
		'attempts' => 'int',
		'max_attempts' => 'int',
		'available_at' => 'datetime',
		'started_at' => 'datetime',
		'finished_at' => 'datetime'
	];

	protected $fillable = [
		'report_type',
		'payload_json',
		'status',
		'attempts',
		'max_attempts',
		'file_path',
		'error_message',
		'available_at',
		'started_at',
		'finished_at'
	];
}
