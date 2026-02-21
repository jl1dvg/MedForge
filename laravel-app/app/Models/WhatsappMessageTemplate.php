<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappMessageTemplate
 * 
 * @property int $id
 * @property string $template_code
 * @property string $display_name
 * @property string $language
 * @property string $category
 * @property string $status
 * @property int|null $current_revision_id
 * @property string|null $wa_business_account
 * @property string|null $description
 * @property Carbon|null $approval_requested_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property User|null $user
 * @property WhatsappTemplateRevision|null $whatsapp_template_revision
 * @property Collection|WhatsappTemplateRevision[] $whatsapp_template_revisions
 *
 * @package App\Models
 */
class WhatsappMessageTemplate extends Model
{
	protected $table = 'whatsapp_message_templates';

	protected $casts = [
		'current_revision_id' => 'int',
		'approval_requested_at' => 'datetime',
		'approved_at' => 'datetime',
		'rejected_at' => 'datetime',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'template_code',
		'display_name',
		'language',
		'category',
		'status',
		'current_revision_id',
		'wa_business_account',
		'description',
		'approval_requested_at',
		'approved_at',
		'rejected_at',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function whatsapp_template_revision()
	{
		return $this->belongsTo(WhatsappTemplateRevision::class, 'current_revision_id');
	}

	public function whatsapp_template_revisions()
	{
		return $this->hasMany(WhatsappTemplateRevision::class, 'template_id');
	}
}
