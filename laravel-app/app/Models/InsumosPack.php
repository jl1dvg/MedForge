<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class InsumosPack
 * 
 * @property string $procedimiento_id
 * @property array $insumos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class InsumosPack extends Model
{
	protected $table = 'insumos_pack';
	protected $primaryKey = 'procedimiento_id';
	public $incrementing = false;

	protected $casts = [
		'insumos' => 'json'
	];

	protected $fillable = [
		'insumos'
	];
}
