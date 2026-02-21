<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmNota
 * 
 * @property int $id
 * @property int $examen_id
 * @property int|null $autor_id
 * @property string $nota
 * @property Carbon $created_at
 * 
 * @property User|null $user
 * @property ConsultaExamene $consulta_examene
 *
 * @package App\Models
 */
class ExamenCrmNota extends Model
{
	protected $table = 'examen_crm_notas';
	public $timestamps = false;

	protected $casts = [
		'examen_id' => 'int',
		'autor_id' => 'int'
	];

	protected $fillable = [
		'examen_id',
		'autor_id',
		'nota'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'autor_id');
	}

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}
}
