<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmMetum
 * 
 * @property int $id
 * @property int $examen_id
 * @property string $meta_key
 * @property string|null $meta_value
 * @property string $meta_type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property ConsultaExamene $consulta_examene
 *
 * @package App\Models
 */
class ExamenCrmMetum extends Model
{
	protected $table = 'examen_crm_meta';

	protected $casts = [
		'examen_id' => 'int'
	];

	protected $fillable = [
		'examen_id',
		'meta_key',
		'meta_value',
		'meta_type'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}
}
