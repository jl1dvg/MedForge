<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderFlowVersion
 * 
 * @property int $id
 * @property int $flow_id
 * @property int $version
 * @property string $status
 * @property string|null $changelog
 * @property array|null $audience_filters
 * @property array|null $entry_settings
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property User|null $user
 * @property WhatsappAutoresponderFlow $whatsapp_autoresponder_flow
 * @property Collection|WhatsappAutoresponderFlow[] $whatsapp_autoresponder_flows
 * @property Collection|WhatsappAutoresponderSchedule[] $whatsapp_autoresponder_schedules
 * @property Collection|WhatsappAutoresponderStep[] $whatsapp_autoresponder_steps
 * @property Collection|WhatsappAutoresponderVersionFilter[] $whatsapp_autoresponder_version_filters
 *
 * @package App\Models
 */
class WhatsappAutoresponderFlowVersion extends Model
{
	protected $table = 'whatsapp_autoresponder_flow_versions';

	protected $casts = [
		'flow_id' => 'int',
		'version' => 'int',
		'audience_filters' => 'json',
		'entry_settings' => 'json',
		'published_at' => 'datetime',
		'published_by' => 'int',
		'created_by' => 'int'
	];

	protected $fillable = [
		'flow_id',
		'version',
		'status',
		'changelog',
		'audience_filters',
		'entry_settings',
		'published_at',
		'published_by',
		'created_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'published_by');
	}

	public function whatsapp_autoresponder_flow()
	{
		return $this->belongsTo(WhatsappAutoresponderFlow::class, 'flow_id');
	}

	public function whatsapp_autoresponder_flows()
	{
		return $this->hasMany(WhatsappAutoresponderFlow::class, 'active_version_id');
	}

	public function whatsapp_autoresponder_schedules()
	{
		return $this->hasMany(WhatsappAutoresponderSchedule::class, 'flow_version_id');
	}

	public function whatsapp_autoresponder_steps()
	{
		return $this->hasMany(WhatsappAutoresponderStep::class, 'flow_version_id');
	}

	public function whatsapp_autoresponder_version_filters()
	{
		return $this->hasMany(WhatsappAutoresponderVersionFilter::class, 'flow_version_id');
	}
}
