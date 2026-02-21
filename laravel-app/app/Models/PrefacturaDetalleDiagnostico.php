<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PrefacturaDetalleDiagnostico
 * 
 * @property int $id
 * @property int $prefactura_id
 * @property int $posicion
 * @property string|null $diagnostico_codigo
 * @property string|null $descripcion
 * @property string|null $lateralidad
 * @property string|null $evidencia
 * @property string|null $observaciones
 * @property array|null $raw
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property PrefacturaPaciente $prefactura_paciente
 *
 * @package App\Models
 */
class PrefacturaDetalleDiagnostico extends Model
{
	protected $table = 'prefactura_detalle_diagnosticos';

	protected $casts = [
		'prefactura_id' => 'int',
		'posicion' => 'int',
		'raw' => 'json'
	];

	protected $fillable = [
		'prefactura_id',
		'posicion',
		'diagnostico_codigo',
		'descripcion',
		'lateralidad',
		'evidencia',
		'observaciones',
		'raw'
	];

	public function prefactura_paciente()
	{
		return $this->belongsTo(PrefacturaPaciente::class, 'prefactura_id');
	}
}
