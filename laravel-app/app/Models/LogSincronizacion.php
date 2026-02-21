<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class LogSincronizacion
 * 
 * @property int $id
 * @property Carbon $fecha
 * @property int|null $total_citas
 * @property int|null $nuevas
 * @property int|null $actualizadas
 * @property Carbon|null $created_at
 *
 * @package App\Models
 */
class LogSincronizacion extends Model
{
	protected $table = 'log_sincronizacion';
	public $timestamps = false;

	protected $casts = [
		'fecha' => 'datetime',
		'total_citas' => 'int',
		'nuevas' => 'int',
		'actualizadas' => 'int'
	];

	protected $fillable = [
		'fecha',
		'total_citas',
		'nuevas',
		'actualizadas'
	];
}
