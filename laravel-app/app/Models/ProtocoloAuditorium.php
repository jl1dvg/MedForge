<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProtocoloAuditorium
 * 
 * @property int $id
 * @property int|null $protocolo_id
 * @property string $form_id
 * @property string $hc_number
 * @property string $evento
 * @property int $status
 * @property int $version
 * @property int|null $usuario_id
 * @property Carbon $creado_en
 *
 * @package App\Models
 */
class ProtocoloAuditorium extends Model
{
	protected $table = 'protocolo_auditoria';
	public $timestamps = false;

	protected $casts = [
		'protocolo_id' => 'int',
		'status' => 'int',
		'version' => 'int',
		'usuario_id' => 'int',
		'creado_en' => 'datetime'
	];

	protected $fillable = [
		'protocolo_id',
		'form_id',
		'hc_number',
		'evento',
		'status',
		'version',
		'usuario_id',
		'creado_en'
	];
}
