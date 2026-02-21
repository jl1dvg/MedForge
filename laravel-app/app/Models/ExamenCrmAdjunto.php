<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmAdjunto
 * 
 * @property int $id
 * @property int $examen_id
 * @property string $nombre_original
 * @property string $ruta_relativa
 * @property string|null $mime_type
 * @property int|null $tamano_bytes
 * @property string|null $descripcion
 * @property int|null $subido_por
 * @property Carbon $created_at
 * 
 * @property ConsultaExamene $consulta_examene
 * @property User|null $user
 *
 * @package App\Models
 */
class ExamenCrmAdjunto extends Model
{
	protected $table = 'examen_crm_adjuntos';
	public $timestamps = false;

	protected $casts = [
		'examen_id' => 'int',
		'tamano_bytes' => 'int',
		'subido_por' => 'int'
	];

	protected $fillable = [
		'examen_id',
		'nombre_original',
		'ruta_relativa',
		'mime_type',
		'tamano_bytes',
		'descripcion',
		'subido_por'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'subido_por');
	}
}
