<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderVersionFilter
 * 
 * @property int $id
 * @property int $flow_version_id
 * @property string $filter_type
 * @property string $operator
 * @property array|null $value
 * @property bool $is_exclusion
 * @property int $order_index
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderFlowVersion $whatsapp_autoresponder_flow_version
 *
 * @package App\Models
 */
class WhatsappAutoresponderVersionFilter extends Model
{
	protected $table = 'whatsapp_autoresponder_version_filters';

	protected $casts = [
		'flow_version_id' => 'int',
		'value' => 'json',
		'is_exclusion' => 'bool',
		'order_index' => 'int'
	];

	protected $fillable = [
		'flow_version_id',
		'filter_type',
		'operator',
		'value',
		'is_exclusion',
		'order_index'
	];

	public function whatsapp_autoresponder_flow_version()
	{
		return $this->belongsTo(WhatsappAutoresponderFlowVersion::class, 'flow_version_id');
	}
}
