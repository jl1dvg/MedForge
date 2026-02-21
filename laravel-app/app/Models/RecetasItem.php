<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class RecetasItem
 * 
 * @property int $id
 * @property int $form_id
 * @property int|null $id_ui
 * @property string|null $estado_receta
 * @property string $producto
 * @property string $vias
 * @property string|null $unidad
 * @property string|null $pauta
 * @property string|null $dosis
 * @property int|null $cantidad
 * @property int|null $total_farmacia
 * @property string|null $observaciones
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class RecetasItem extends Model
{
	protected $table = 'recetas_items';

	protected $casts = [
		'form_id' => 'int',
		'id_ui' => 'int',
		'cantidad' => 'int',
		'total_farmacia' => 'int'
	];

	protected $fillable = [
		'form_id',
		'id_ui',
		'estado_receta',
		'producto',
		'vias',
		'unidad',
		'pauta',
		'dosis',
		'cantidad',
		'total_farmacia',
		'observaciones'
	];
}
