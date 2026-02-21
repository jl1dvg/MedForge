<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderSchedule
 * 
 * @property int $id
 * @property int $flow_version_id
 * @property int|null $day_of_week
 * @property Carbon $start_time
 * @property Carbon $end_time
 * @property string|null $timezone
 * @property bool $allow_holidays
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderFlowVersion $whatsapp_autoresponder_flow_version
 *
 * @package App\Models
 */
class WhatsappAutoresponderSchedule extends Model
{
	protected $table = 'whatsapp_autoresponder_schedules';

	protected $casts = [
		'flow_version_id' => 'int',
		'day_of_week' => 'int',
		'start_time' => 'datetime',
		'end_time' => 'datetime',
		'allow_holidays' => 'bool'
	];

	protected $fillable = [
		'flow_version_id',
		'day_of_week',
		'start_time',
		'end_time',
		'timezone',
		'allow_holidays'
	];

	public function whatsapp_autoresponder_flow_version()
	{
		return $this->belongsTo(WhatsappAutoresponderFlowVersion::class, 'flow_version_id');
	}
}
