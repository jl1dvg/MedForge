<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UserMediaHistory
 * 
 * @property int $id
 * @property int $user_id
 * @property string $media_type
 * @property int $version
 * @property string $action
 * @property string|null $path
 * @property string|null $mime
 * @property int|null $size
 * @property string|null $hash
 * @property string|null $previous_path
 * @property string|null $status
 * @property int|null $acted_by
 * @property Carbon $acted_at
 *
 * @package App\Models
 */
class UserMediaHistory extends Model
{
	protected $table = 'user_media_history';
	public $timestamps = false;

	protected $casts = [
		'user_id' => 'int',
		'version' => 'int',
		'size' => 'int',
		'acted_by' => 'int',
		'acted_at' => 'datetime'
	];

	protected $fillable = [
		'user_id',
		'media_type',
		'version',
		'action',
		'path',
		'mime',
		'size',
		'hash',
		'previous_path',
		'status',
		'acted_by',
		'acted_at'
	];
}
