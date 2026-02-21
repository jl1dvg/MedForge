<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudCrmAdjunto
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string $nombre_original
 * @property string $ruta_relativa
 * @property string|null $mime_type
 * @property int|null $tamano_bytes
 * @property string|null $descripcion
 * @property int|null $subido_por
 * @property Carbon|null $created_at
 * 
 * @property SolicitudProcedimiento $solicitud_procedimiento
 * @property User|null $user
 *
 * @package App\Models
 */
class SolicitudCrmAdjunto extends Model
{
	protected $table = 'solicitud_crm_adjuntos';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'tamano_bytes' => 'int',
		'subido_por' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'nombre_original',
		'ruta_relativa',
		'mime_type',
		'tamano_bytes',
		'descripcion',
		'subido_por'
	];

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'subido_por');
	}
}
