<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappTemplateRevision
 * 
 * @property int $id
 * @property int $template_id
 * @property int $version
 * @property string $status
 * @property string $header_type
 * @property string|null $header_text
 * @property string $body_text
 * @property string|null $footer_text
 * @property array|null $buttons
 * @property array|null $variables
 * @property string $quality_rating
 * @property string|null $rejection_reason
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property User|null $user
 * @property WhatsappMessageTemplate $whatsapp_message_template
 * @property Collection|WhatsappAutoresponderStepAction[] $whatsapp_autoresponder_step_actions
 * @property Collection|WhatsappMessageTemplate[] $whatsapp_message_templates
 *
 * @package App\Models
 */
class WhatsappTemplateRevision extends Model
{
	protected $table = 'whatsapp_template_revisions';

	protected $casts = [
		'template_id' => 'int',
		'version' => 'int',
		'buttons' => 'json',
		'variables' => 'json',
		'submitted_at' => 'datetime',
		'approved_at' => 'datetime',
		'rejected_at' => 'datetime',
		'created_by' => 'int'
	];

	protected $fillable = [
		'template_id',
		'version',
		'status',
		'header_type',
		'header_text',
		'body_text',
		'footer_text',
		'buttons',
		'variables',
		'quality_rating',
		'rejection_reason',
		'submitted_at',
		'approved_at',
		'rejected_at',
		'created_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	public function whatsapp_message_template()
	{
		return $this->belongsTo(WhatsappMessageTemplate::class, 'template_id');
	}

	public function whatsapp_autoresponder_step_actions()
	{
		return $this->hasMany(WhatsappAutoresponderStepAction::class, 'template_revision_id');
	}

	public function whatsapp_message_templates()
	{
		return $this->hasMany(WhatsappMessageTemplate::class, 'current_revision_id');
	}
}
