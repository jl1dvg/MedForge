<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class KpiSnapshot
 * 
 * @property int $id
 * @property string $kpi_key
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $period_granularity
 * @property string $dimension_hash
 * @property array|null $dimensions_json
 * @property float $value
 * @property float|null $numerator
 * @property float|null $denominator
 * @property array|null $extra_json
 * @property Carbon $computed_at
 * @property string|null $source_version
 *
 * @package App\Models
 */
class KpiSnapshot extends Model
{
	protected $table = 'kpi_snapshots';
	public $timestamps = false;

	protected $casts = [
		'period_start' => 'datetime',
		'period_end' => 'datetime',
		'dimensions_json' => 'json',
		'value' => 'float',
		'numerator' => 'float',
		'denominator' => 'float',
		'extra_json' => 'json',
		'computed_at' => 'datetime'
	];

	protected $fillable = [
		'kpi_key',
		'period_start',
		'period_end',
		'period_granularity',
		'dimension_hash',
		'dimensions_json',
		'value',
		'numerator',
		'denominator',
		'extra_json',
		'computed_at',
		'source_version'
	];
}
