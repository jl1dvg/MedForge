<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ExamenCrmDetalle
 * 
 * @property int $examen_id
 * @property int|null $crm_lead_id
 * @property int|null $responsable_id
 * @property string|null $pipeline_stage
 * @property string|null $fuente
 * @property string|null $contacto_email
 * @property string|null $contacto_telefono
 * @property array|null $followers
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property ConsultaExamene $consulta_examene
 * @property CrmLead|null $crm_lead
 * @property User|null $user
 *
 * @package App\Models
 */
class ExamenCrmDetalle extends Model
{
	protected $table = 'examen_crm_detalles';
	protected $primaryKey = 'examen_id';
	public $incrementing = false;

	protected $casts = [
		'examen_id' => 'int',
		'crm_lead_id' => 'int',
		'responsable_id' => 'int',
		'followers' => 'json'
	];

	protected $fillable = [
		'crm_lead_id',
		'responsable_id',
		'pipeline_stage',
		'fuente',
		'contacto_email',
		'contacto_telefono',
		'followers'
	];

	public function consulta_examene()
	{
		return $this->belongsTo(ConsultaExamene::class, 'examen_id');
	}

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'responsable_id');
	}
}
