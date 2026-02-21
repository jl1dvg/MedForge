<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Evolucion005
 * 
 * @property string $id
 * @property string|null $pre_evolucion
 * @property string|null $pre_indicacion
 * @property string|null $post_evolucion
 * @property string|null $post_indicacion
 * @property string|null $alta_evolucion
 * @property string|null $alta_indicacion
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Procedimiento $procedimiento
 *
 * @package App\Models
 */
class Evolucion005 extends Model
{
	protected $table = 'evolucion005';
	public $incrementing = false;

	protected $fillable = [
		'pre_evolucion',
		'pre_indicacion',
		'post_evolucion',
		'post_indicacion',
		'alta_evolucion',
		'alta_indicacion'
	];

	public function procedimiento()
	{
		return $this->belongsTo(Procedimiento::class, 'id');
	}
}
