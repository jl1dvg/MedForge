<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderFlow
 * 
 * @property int $id
 * @property string $flow_key
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string|null $timezone
 * @property Carbon|null $active_from
 * @property Carbon|null $active_until
 * @property int|null $active_version_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderFlowVersion|null $whatsapp_autoresponder_flow_version
 * @property User|null $user
 * @property Collection|WhatsappAutoresponderFlowVersion[] $whatsapp_autoresponder_flow_versions
 *
 * @package App\Models
 */
class WhatsappAutoresponderFlow extends Model
{
	protected $table = 'whatsapp_autoresponder_flows';

	protected $casts = [
		'active_from' => 'datetime',
		'active_until' => 'datetime',
		'active_version_id' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'flow_key',
		'name',
		'description',
		'status',
		'timezone',
		'active_from',
		'active_until',
		'active_version_id',
		'created_by',
		'updated_by'
	];

	public function whatsapp_autoresponder_flow_version()
	{
		return $this->belongsTo(WhatsappAutoresponderFlowVersion::class, 'active_version_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function whatsapp_autoresponder_flow_versions()
	{
		return $this->hasMany(WhatsappAutoresponderFlowVersion::class, 'flow_id');
	}
}
