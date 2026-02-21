<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenMailLog
 * 
 * @property int $id
 * @property int|null $examen_id
 * @property string|null $form_id
 * @property string|null $hc_number
 * @property string $to_emails
 * @property string|null $cc_emails
 * @property string $subject
 * @property string|null $body_text
 * @property string|null $body_html
 * @property string $channel
 * @property int|null $sent_by_user_id
 * @property string $status
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 * 
 * @property ConsultaExamene|null $consulta_examene
 * @property User|null $user
 *
 * @package App\Models
 */
class ExamenMailLog extends Model
{
	protected $table = 'examen_mail_log';
	public $timestamps = false;

	protected $casts = [
		'examen_id' => 'int',
		'sent_by_user_id' => 'int',
		'sent_at' => 'datetime'
	];

	protected $fillable = [
		'examen_id',
		'form_id',
		'hc_number',
		'to_emails',
		'cc_emails',
		'subject',
		'body_text',
		'body_html',
		'channel',
		'sent_by_user_id',
		'status',
		'error_message',
		'sent_at'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'sent_by_user_id');
	}
}
