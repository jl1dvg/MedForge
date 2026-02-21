<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SigcenterAfiliacione
 * 
 * @property int $id
 * @property int $sigcenter_id
 * @property string $nombre
 * @property bool|null $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class SigcenterAfiliacione extends Model
{
	protected $table = 'sigcenter_afiliaciones';

	protected $casts = [
		'sigcenter_id' => 'int',
		'activo' => 'bool'
	];

	protected $fillable = [
		'sigcenter_id',
		'nombre',
		'activo'
	];
}
