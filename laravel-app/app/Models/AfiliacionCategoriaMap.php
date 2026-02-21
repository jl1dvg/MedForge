<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class AfiliacionCategoriaMap
 * 
 * @property int $id
 * @property string $afiliacion_raw
 * @property string $afiliacion_norm
 * @property string $categoria
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class AfiliacionCategoriaMap extends Model
{
	protected $table = 'afiliacion_categoria_map';

	protected $fillable = [
		'afiliacion_raw',
		'afiliacion_norm',
		'categoria'
	];
}
