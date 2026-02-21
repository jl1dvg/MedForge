<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappAutoresponderStepAction
 * 
 * @property int $id
 * @property int $step_id
 * @property string $action_type
 * @property int|null $template_revision_id
 * @property string|null $message_body
 * @property string|null $media_url
 * @property int|null $delay_seconds
 * @property array|null $metadata
 * @property int $order_index
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property WhatsappAutoresponderStep $whatsapp_autoresponder_step
 * @property WhatsappTemplateRevision|null $whatsapp_template_revision
 *
 * @package App\Models
 */
class WhatsappAutoresponderStepAction extends Model
{
	protected $table = 'whatsapp_autoresponder_step_actions';

	protected $casts = [
		'step_id' => 'int',
		'template_revision_id' => 'int',
		'delay_seconds' => 'int',
		'metadata' => 'json',
		'order_index' => 'int'
	];

	protected $fillable = [
		'step_id',
		'action_type',
		'template_revision_id',
		'message_body',
		'media_url',
		'delay_seconds',
		'metadata',
		'order_index'
	];

	public function whatsapp_autoresponder_step()
	{
		return $this->belongsTo(WhatsappAutoresponderStep::class, 'step_id');
	}

	public function whatsapp_template_revision()
	{
		return $this->belongsTo(WhatsappTemplateRevision::class, 'template_revision_id');
	}
}
