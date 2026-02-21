<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class UsersOld
 * 
 * @property int $id
 * @property string $username
 * @property string|null $password
 * @property string|null $remember_token
 * @property string|null $biografia
 * @property string|null $email
 * @property bool|null $is_subscribed
 * @property bool|null $is_approved
 * @property Carbon|null $created_at
 * @property string|null $nombre
 * @property string|null $cedula
 * @property string|null $registro
 * @property string|null $sede
 * @property string|null $firma
 * @property string|null $role
 * @property string|null $profile_photo
 * @property string|null $especialidad
 * @property string|null $subespecialidad
 * @property string|null $permisos
 * @property int|null $role_id
 *
 * @package App\Models
 */
class UsersOld extends Model
{
	protected $table = 'users_old';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'id' => 'int',
		'is_subscribed' => 'bool',
		'is_approved' => 'bool',
		'role_id' => 'int'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'username',
		'password',
		'remember_token',
		'biografia',
		'email',
		'is_subscribed',
		'is_approved',
		'nombre',
		'cedula',
		'registro',
		'sede',
		'firma',
		'role',
		'profile_photo',
		'especialidad',
		'subespecialidad',
		'permisos',
		'role_id'
	];
}
