<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudCrmDetalle
 * 
 * @property int $solicitud_id
 * @property int|null $crm_lead_id
 * @property int|null $crm_project_id
 * @property int|null $responsable_id
 * @property string|null $contacto_email
 * @property string|null $contacto_telefono
 * @property string|null $fuente
 * @property string|null $pipeline_stage
 * @property string|null $followers
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property CrmLead|null $crm_lead
 * @property CrmProject|null $crm_project
 * @property User|null $user
 * @property SolicitudProcedimiento $solicitud_procedimiento
 *
 * @package App\Models
 */
class SolicitudCrmDetalle extends Model
{
	protected $table = 'solicitud_crm_detalles';
	protected $primaryKey = 'solicitud_id';
	public $incrementing = false;

	protected $casts = [
		'solicitud_id' => 'int',
		'crm_lead_id' => 'int',
		'crm_project_id' => 'int',
		'responsable_id' => 'int'
	];

	protected $fillable = [
		'crm_lead_id',
		'crm_project_id',
		'responsable_id',
		'contacto_email',
		'contacto_telefono',
		'fuente',
		'pipeline_stage',
		'followers'
	];

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class);
	}

	public function crm_project()
	{
		return $this->belongsTo(CrmProject::class);
	}

	public function user()
	{
		return $this->belongsTo(User::class, 'responsable_id');
	}

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}
}
