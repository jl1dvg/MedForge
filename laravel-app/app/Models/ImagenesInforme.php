<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ImagenesInforme
 * 
 * @property int $id
 * @property string $form_id
 * @property string|null $hc_number
 * @property string $tipo_examen
 * @property string $plantilla
 * @property array $payload_json
 * @property int|null $firmado_por
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 *
 * @package App\Models
 */
class ImagenesInforme extends Model
{
	protected $table = 'imagenes_informes';

	protected $casts = [
		'payload_json' => 'json',
		'firmado_por' => 'int',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'form_id',
		'hc_number',
		'tipo_examen',
		'plantilla',
		'payload_json',
		'firmado_por',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}
}
