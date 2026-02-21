<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudChecklistLog
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string $etapa_slug
 * @property string $accion
 * @property int|null $actor_id
 * @property string|null $nota
 * @property Carbon|null $old_completado_at
 * @property Carbon|null $new_completado_at
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class SolicitudChecklistLog extends Model
{
	protected $table = 'solicitud_checklist_log';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'actor_id' => 'int',
		'old_completado_at' => 'datetime',
		'new_completado_at' => 'datetime'
	];

	protected $fillable = [
		'solicitud_id',
		'etapa_slug',
		'accion',
		'actor_id',
		'nota',
		'old_completado_at',
		'new_completado_at'
	];
}
