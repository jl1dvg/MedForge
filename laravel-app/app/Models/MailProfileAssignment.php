<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MailProfileAssignment
 * 
 * @property int $id
 * @property string $context
 * @property string $profile_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property MailProfile $mail_profile
 *
 * @package App\Models
 */
class MailProfileAssignment extends Model
{
	protected $table = 'mail_profile_assignments';

	protected $fillable = [
		'context',
		'profile_slug'
	];

	public function mail_profile()
	{
		return $this->belongsTo(MailProfile::class, 'profile_slug', 'slug');
	}
}
