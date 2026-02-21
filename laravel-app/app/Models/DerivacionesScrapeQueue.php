<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesScrapeQueue
 * 
 * @property int $id
 * @property string $form_id
 * @property string|null $hc_number
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $next_retry_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class DerivacionesScrapeQueue extends Model
{
	protected $table = 'derivaciones_scrape_queue';

	protected $casts = [
		'attempts' => 'int',
		'next_retry_at' => 'datetime',
		'last_attempt_at' => 'datetime'
	];

	protected $fillable = [
		'form_id',
		'hc_number',
		'status',
		'attempts',
		'last_error',
		'next_retry_at',
		'last_attempt_at'
	];
}
