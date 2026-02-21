<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderStepTransition
 * 
 * @property int $id
 * @property int $step_id
 * @property int|null $target_step_id
 * @property string|null $condition_label
 * @property string $condition_type
 * @property array|null $condition_payload
 * @property int $priority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderStep|null $whatsapp_autoresponder_step
 *
 * @package App\Models
 */
class WhatsappAutoresponderStepTransition extends Model
{
	protected $table = 'whatsapp_autoresponder_step_transitions';

	protected $casts = [
		'step_id' => 'int',
		'target_step_id' => 'int',
		'condition_payload' => 'json',
		'priority' => 'int'
	];

	protected $fillable = [
		'step_id',
		'target_step_id',
		'condition_label',
		'condition_type',
		'condition_payload',
		'priority'
	];

	public function whatsapp_autoresponder_step()
	{
		return $this->belongsTo(WhatsappAutoresponderStep::class, 'target_step_id');
	}
}
