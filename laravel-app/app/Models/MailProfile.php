<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MailProfile
 * 
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $engine
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_encryption
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $from_address
 * @property string|null $from_name
 * @property string|null $reply_to_address
 * @property string|null $reply_to_name
 * @property string|null $header
 * @property string|null $footer
 * @property string|null $signature
 * @property int|null $smtp_timeout_seconds
 * @property bool $smtp_debug_enabled
 * @property bool $smtp_allow_self_signed
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|MailProfileAssignment[] $mail_profile_assignments
 *
 * @package App\Models
 */
class MailProfile extends Model
{
	protected $table = 'mail_profiles';

	protected $casts = [
		'smtp_port' => 'int',
		'smtp_timeout_seconds' => 'int',
		'smtp_debug_enabled' => 'bool',
		'smtp_allow_self_signed' => 'bool',
		'active' => 'bool'
	];

	protected $hidden = [
		'smtp_password'
	];

	protected $fillable = [
		'slug',
		'name',
		'engine',
		'smtp_host',
		'smtp_port',
		'smtp_encryption',
		'smtp_username',
		'smtp_password',
		'from_address',
		'from_name',
		'reply_to_address',
		'reply_to_name',
		'header',
		'footer',
		'signature',
		'smtp_timeout_seconds',
		'smtp_debug_enabled',
		'smtp_allow_self_signed',
		'active'
	];

	public function mail_profile_assignments()
	{
		return $this->hasMany(MailProfileAssignment::class, 'profile_slug', 'slug');
	}
}
