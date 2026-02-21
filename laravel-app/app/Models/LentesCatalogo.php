<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class LentesCatalogo
 * 
 * @property int $id
 * @property string $marca
 * @property string $modelo
 * @property string $nombre
 * @property string|null $poder
 * @property float|null $rango_desde
 * @property float|null $rango_hasta
 * @property float|null $rango_paso
 * @property float|null $rango_inicio_incremento
 * @property string|null $rango_texto
 * @property float|null $constante_a
 * @property float|null $constante_a_us
 * @property string|null $tipo_optico
 * @property string|null $observacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class LentesCatalogo extends Model
{
	protected $table = 'lentes_catalogo';

	protected $casts = [
		'rango_desde' => 'float',
		'rango_hasta' => 'float',
		'rango_paso' => 'float',
		'rango_inicio_incremento' => 'float',
		'constante_a' => 'float',
		'constante_a_us' => 'float'
	];

	protected $fillable = [
		'marca',
		'modelo',
		'nombre',
		'poder',
		'rango_desde',
		'rango_hasta',
		'rango_paso',
		'rango_inicio_incremento',
		'rango_texto',
		'constante_a',
		'constante_a_us',
		'tipo_optico',
		'observacion'
	];
}
