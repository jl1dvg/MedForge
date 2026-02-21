<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class StgProcedimientosProyectado
 * 
 * @property string $raw_text
 * @property int $total
 *
 * @package App\Models
 */
class StgProcedimientosProyectado extends Model
{
	protected $table = 'stg_procedimientos_proyectado';
	public $incrementing = false;
	public $timestamps = false;

	protected $casts = [
		'total' => 'int'
	];

	protected $fillable = [
		'raw_text',
		'total'
	];
}
