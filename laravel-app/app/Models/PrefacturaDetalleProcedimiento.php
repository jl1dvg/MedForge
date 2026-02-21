<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PrefacturaDetalleProcedimiento
 * 
 * @property int $id
 * @property int $prefactura_id
 * @property int $posicion
 * @property string|null $external_id
 * @property string|null $proc_interno
 * @property string|null $codigo
 * @property string|null $descripcion
 * @property string|null $lateralidad
 * @property string|null $observaciones
 * @property float|null $precio_base
 * @property float|null $precio_tarifado
 * @property array|null $raw
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property PrefacturaPaciente $prefactura_paciente
 *
 * @package App\Models
 */
class PrefacturaDetalleProcedimiento extends Model
{
	protected $table = 'prefactura_detalle_procedimientos';

	protected $casts = [
		'prefactura_id' => 'int',
		'posicion' => 'int',
		'precio_base' => 'float',
		'precio_tarifado' => 'float',
		'raw' => 'json'
	];

	protected $fillable = [
		'prefactura_id',
		'posicion',
		'external_id',
		'proc_interno',
		'codigo',
		'descripcion',
		'lateralidad',
		'observaciones',
		'precio_base',
		'precio_tarifado',
		'raw'
	];

	public function prefactura_paciente()
	{
		return $this->belongsTo(PrefacturaPaciente::class, 'prefactura_id');
	}
}
