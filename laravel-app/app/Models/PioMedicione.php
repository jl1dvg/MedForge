<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PioMedicione
 * 
 * @property int $id
 * @property int $form_id
 * @property int|null $id_ui
 * @property string|null $tonometro
 * @property float|null $od
 * @property float|null $oi
 * @property bool $patologico
 * @property Carbon|null $hora
 * @property Carbon|null $hora_fin
 * @property string|null $observacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class PioMedicione extends Model
{
	protected $table = 'pio_mediciones';

	protected $casts = [
		'form_id' => 'int',
		'id_ui' => 'int',
		'od' => 'float',
		'oi' => 'float',
		'patologico' => 'bool',
		'hora' => 'datetime',
		'hora_fin' => 'datetime'
	];

	protected $fillable = [
		'form_id',
		'id_ui',
		'tonometro',
		'od',
		'oi',
		'patologico',
		'hora',
		'hora_fin',
		'observacion'
	];
}
