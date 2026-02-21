<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class KpiDimension
 * 
 * @property int $id
 * @property string $dimension_key
 * @property string $raw_value
 * @property string $normalized_value
 * @property array|null $metadata_json
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class KpiDimension extends Model
{
	protected $table = 'kpi_dimensions';

	protected $casts = [
		'metadata_json' => 'json'
	];

	protected $fillable = [
		'dimension_key',
		'raw_value',
		'normalized_value',
		'metadata_json'
	];
}
