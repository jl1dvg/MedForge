<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CiveExtensionHealthCheck
 * 
 * @property int $id
 * @property string $endpoint
 * @property string $method
 * @property int|null $status_code
 * @property bool $success
 * @property int|null $latency_ms
 * @property string|null $error_message
 * @property string|null $response_excerpt
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class CiveExtensionHealthCheck extends Model
{
	protected $table = 'cive_extension_health_checks';
	public $timestamps = false;

	protected $casts = [
		'status_code' => 'int',
		'success' => 'bool',
		'latency_ms' => 'int'
	];

	protected $fillable = [
		'endpoint',
		'method',
		'status_code',
		'success',
		'latency_ms',
		'error_message',
		'response_excerpt'
	];
}
