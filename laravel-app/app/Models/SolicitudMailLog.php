<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudMailLog
 * 
 * @property int $id
 * @property int|null $solicitud_id
 * @property string|null $form_id
 * @property string|null $hc_number
 * @property string|null $afiliacion
 * @property string|null $template_key
 * @property string $to_emails
 * @property string|null $cc_emails
 * @property string $subject
 * @property string|null $body_text
 * @property string|null $body_html
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property int|null $attachment_size
 * @property int|null $sent_by_user_id
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class SolicitudMailLog extends Model
{
	protected $table = 'solicitud_mail_log';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'attachment_size' => 'int',
		'sent_by_user_id' => 'int',
		'sent_at' => 'datetime'
	];

	protected $fillable = [
		'solicitud_id',
		'form_id',
		'hc_number',
		'afiliacion',
		'template_key',
		'to_emails',
		'cc_emails',
		'subject',
		'body_text',
		'body_html',
		'attachment_path',
		'attachment_name',
		'attachment_size',
		'sent_by_user_id',
		'status',
		'error_message',
		'sent_at'
	];
}
