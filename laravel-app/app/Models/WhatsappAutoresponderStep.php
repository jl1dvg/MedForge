<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderStep
 * 
 * @property int $id
 * @property int $flow_version_id
 * @property string $step_key
 * @property string $step_type
 * @property string $name
 * @property string|null $description
 * @property int $order_index
 * @property bool $is_entry_point
 * @property array|null $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderFlowVersion $whatsapp_autoresponder_flow_version
 * @property Collection|WhatsappAutoresponderStepAction[] $whatsapp_autoresponder_step_actions
 * @property Collection|WhatsappAutoresponderStepTransition[] $whatsapp_autoresponder_step_transitions
 *
 * @package App\Models
 */
class WhatsappAutoresponderStep extends Model
{
	protected $table = 'whatsapp_autoresponder_steps';

	protected $casts = [
		'flow_version_id' => 'int',
		'order_index' => 'int',
		'is_entry_point' => 'bool',
		'settings' => 'json'
	];

	protected $fillable = [
		'flow_version_id',
		'step_key',
		'step_type',
		'name',
		'description',
		'order_index',
		'is_entry_point',
		'settings'
	];

	public function whatsapp_autoresponder_flow_version()
	{
		return $this->belongsTo(WhatsappAutoresponderFlowVersion::class, 'flow_version_id');
	}

	public function whatsapp_autoresponder_step_actions()
	{
		return $this->hasMany(WhatsappAutoresponderStepAction::class, 'step_id');
	}

	public function whatsapp_autoresponder_step_transitions()
	{
		return $this->hasMany(WhatsappAutoresponderStepTransition::class, 'target_step_id');
	}
}
