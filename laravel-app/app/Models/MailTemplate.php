<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MailTemplate
 * 
 * @property int $id
 * @property string $context
 * @property string $template_key
 * @property string $name
 * @property string|null $subject_template
 * @property string|null $body_template_html
 * @property string|null $body_template_text
 * @property string|null $recipients_to
 * @property string|null $recipients_cc
 * @property bool $enabled
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class MailTemplate extends Model
{
	protected $table = 'mail_templates';

	protected $casts = [
		'enabled' => 'bool',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'context',
		'template_key',
		'name',
		'subject_template',
		'body_template_html',
		'body_template_text',
		'recipients_to',
		'recipients_cc',
		'enabled',
		'updated_by'
	];
}
