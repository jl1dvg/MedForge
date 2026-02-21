<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudCrmNota
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property int|null $autor_id
 * @property string $nota
 * @property Carbon|null $created_at
 * 
 * @property User|null $user
 * @property SolicitudProcedimiento $solicitud_procedimiento
 *
 * @package App\Models
 */
class SolicitudCrmNota extends Model
{
	protected $table = 'solicitud_crm_notas';
	public $timestamps = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'autor_id' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'autor_id',
		'nota'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'autor_id');
	}

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}
}
