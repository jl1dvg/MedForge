<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudChecklist
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string $etapa_slug
 * @property bool $checked
 * @property Carbon|null $completado_at
 * @property int|null $completado_por
 * @property string|null $nota
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class SolicitudChecklist extends Model
{
	protected $table = 'solicitud_checklist';

	protected $casts = [
		'solicitud_id' => 'int',
		'checked' => 'bool',
		'completado_at' => 'datetime',
		'completado_por' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'etapa_slug',
		'checked',
		'completado_at',
		'completado_por',
		'nota'
	];
}
