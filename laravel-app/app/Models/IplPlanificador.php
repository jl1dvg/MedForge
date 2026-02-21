<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class IplPlanificador
 * 
 * @property int $id
 * @property int $hc_number
 * @property int|null $form_id_origen
 * @property int $nro_sesion
 * @property Carbon $fecha_ficticia
 * @property string|null $form_id_real
 * @property string|null $estado
 * @property int $derivacion_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $doctor
 * @property string|null $procedimiento
 * @property string|null $diagnostico
 * 
 * @property DerivacionesFormId $derivaciones_form_id
 * @property ProtocoloDatum|null $protocolo_datum
 *
 * @package App\Models
 */
class IplPlanificador extends Model
{
	protected $table = 'ipl_planificador';

	protected $casts = [
		'hc_number' => 'int',
		'form_id_origen' => 'int',
		'nro_sesion' => 'int',
		'fecha_ficticia' => 'datetime',
		'derivacion_id' => 'int'
	];

	protected $fillable = [
		'hc_number',
		'form_id_origen',
		'nro_sesion',
		'fecha_ficticia',
		'form_id_real',
		'estado',
		'derivacion_id',
		'doctor',
		'procedimiento',
		'diagnostico'
	];

	public function derivaciones_form_id()
	{
		return $this->belongsTo(DerivacionesFormId::class, 'derivacion_id');
	}

	public function protocolo_datum()
	{
		return $this->belongsTo(ProtocoloDatum::class, 'form_id_real', 'form_id');
	}
}
