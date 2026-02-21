<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class User
 * 
 * @property int $id
 * @property string $username
 * @property string $first_name
 * @property string $middle_name
 * @property string $last_name
 * @property string $second_last_name
 * @property Carbon|null $birth_date
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $biografia
 * @property string $email
 * @property string|null $whatsapp_number
 * @property bool $whatsapp_notify
 * @property bool|null $is_subscribed
 * @property bool|null $is_approved
 * @property Carbon|null $created_at
 * @property string $nombre
 * @property string $cedula
 * @property string|null $national_id_encrypted
 * @property string|null $passport_number_encrypted
 * @property string $registro
 * @property string $sede
 * @property string|null $firma
 * @property string|null $firma_mime
 * @property int|null $firma_size
 * @property string|null $firma_hash
 * @property Carbon|null $firma_created_at
 * @property int|null $firma_created_by
 * @property Carbon|null $firma_updated_at
 * @property int|null $firma_updated_by
 * @property Carbon|null $firma_verified_at
 * @property int|null $firma_verified_by
 * @property Carbon|null $firma_deleted_at
 * @property int|null $firma_deleted_by
 * @property string|null $seal_status
 * @property Carbon|null $seal_status_updated_at
 * @property int|null $seal_status_updated_by
 * @property string|null $role
 * @property string|null $profile_photo
 * @property string|null $signature_path
 * @property string|null $signature_mime
 * @property int|null $signature_size
 * @property string|null $signature_hash
 * @property Carbon|null $signature_created_at
 * @property int|null $signature_created_by
 * @property Carbon|null $signature_updated_at
 * @property int|null $signature_updated_by
 * @property Carbon|null $signature_verified_at
 * @property int|null $signature_verified_by
 * @property Carbon|null $signature_deleted_at
 * @property int|null $signature_deleted_by
 * @property string|null $signature_status
 * @property Carbon|null $signature_status_updated_at
 * @property int|null $signature_status_updated_by
 * @property string $especialidad
 * @property string|null $subespecialidad
 * @property string|null $permisos
 * @property int|null $role_id
 * @property string|null $nombre_norm
 * @property string|null $full_name
 * @property string|null $nombre_norm_rev
 * @property int|null $id_trabajador
 * 
 * @property Collection|AgendaCita[] $agenda_citas
 * @property Collection|CrmCalendarBlock[] $crm_calendar_blocks
 * @property Collection|CrmLead[] $crm_leads
 * @property Collection|CrmPackage[] $crm_packages
 * @property Collection|CrmProject[] $crm_projects
 * @property Collection|CrmProposal[] $crm_proposals
 * @property Collection|CrmTaskEvidence[] $crm_task_evidences
 * @property Collection|CrmTask[] $crm_tasks
 * @property Collection|CrmTicketMessage[] $crm_ticket_messages
 * @property Collection|CrmTicket[] $crm_tickets
 * @property Collection|ExamenCrmAdjunto[] $examen_crm_adjuntos
 * @property Collection|ExamenCrmCalendarBlock[] $examen_crm_calendar_blocks
 * @property Collection|ExamenCrmDetalle[] $examen_crm_detalles
 * @property Collection|ExamenCrmNota[] $examen_crm_notas
 * @property Collection|ExamenCrmTarea[] $examen_crm_tareas
 * @property Collection|ExamenEstadoLog[] $examen_estado_logs
 * @property Collection|ExamenMailLog[] $examen_mail_logs
 * @property Collection|FlowmakerFlow[] $flowmaker_flows
 * @property Collection|ImagenesInforme[] $imagenes_informes
 * @property Collection|SolicitudCrmAdjunto[] $solicitud_crm_adjuntos
 * @property Collection|SolicitudCrmDetalle[] $solicitud_crm_detalles
 * @property Collection|SolicitudCrmNota[] $solicitud_crm_notas
 * @property Collection|SolicitudCrmTarea[] $solicitud_crm_tareas
 * @property Collection|TurneroReset[] $turnero_resets
 * @property Collection|WhatsappAutoresponderFlowVersion[] $whatsapp_autoresponder_flow_versions
 * @property Collection|WhatsappAutoresponderFlow[] $whatsapp_autoresponder_flows
 * @property Collection|WhatsappMessageTemplate[] $whatsapp_message_templates
 * @property Collection|WhatsappTemplateRevision[] $whatsapp_template_revisions
 *
 * @package App\Models
 */
class User extends Model
{
	protected $table = 'users';
	public $timestamps = false;

	protected $casts = [
		'birth_date' => 'datetime',
		'whatsapp_notify' => 'bool',
		'is_subscribed' => 'bool',
		'is_approved' => 'bool',
		'firma_size' => 'int',
		'firma_created_at' => 'datetime',
		'firma_created_by' => 'int',
		'firma_updated_at' => 'datetime',
		'firma_updated_by' => 'int',
		'firma_verified_at' => 'datetime',
		'firma_verified_by' => 'int',
		'firma_deleted_at' => 'datetime',
		'firma_deleted_by' => 'int',
		'seal_status_updated_at' => 'datetime',
		'seal_status_updated_by' => 'int',
		'signature_size' => 'int',
		'signature_created_at' => 'datetime',
		'signature_created_by' => 'int',
		'signature_updated_at' => 'datetime',
		'signature_updated_by' => 'int',
		'signature_verified_at' => 'datetime',
		'signature_verified_by' => 'int',
		'signature_deleted_at' => 'datetime',
		'signature_deleted_by' => 'int',
		'signature_status_updated_at' => 'datetime',
		'signature_status_updated_by' => 'int',
		'role_id' => 'int',
		'id_trabajador' => 'int'
	];

	protected $hidden = [
		'password',
		'remember_token'
	];

	protected $fillable = [
		'username',
		'first_name',
		'middle_name',
		'last_name',
		'second_last_name',
		'birth_date',
		'password',
		'remember_token',
		'biografia',
		'email',
		'whatsapp_number',
		'whatsapp_notify',
		'is_subscribed',
		'is_approved',
		'nombre',
		'cedula',
		'national_id_encrypted',
		'passport_number_encrypted',
		'registro',
		'sede',
		'firma',
		'firma_mime',
		'firma_size',
		'firma_hash',
		'firma_created_at',
		'firma_created_by',
		'firma_updated_at',
		'firma_updated_by',
		'firma_verified_at',
		'firma_verified_by',
		'firma_deleted_at',
		'firma_deleted_by',
		'seal_status',
		'seal_status_updated_at',
		'seal_status_updated_by',
		'role',
		'profile_photo',
		'signature_path',
		'signature_mime',
		'signature_size',
		'signature_hash',
		'signature_created_at',
		'signature_created_by',
		'signature_updated_at',
		'signature_updated_by',
		'signature_verified_at',
		'signature_verified_by',
		'signature_deleted_at',
		'signature_deleted_by',
		'signature_status',
		'signature_status_updated_at',
		'signature_status_updated_by',
		'especialidad',
		'subespecialidad',
		'permisos',
		'role_id',
		'nombre_norm',
		'full_name',
		'nombre_norm_rev',
		'id_trabajador'
	];

	public function role()
	{
		return $this->belongsTo(Role::class);
	}

	public function agenda_citas()
	{
		return $this->hasMany(AgendaCita::class, 'created_by');
	}

	public function crm_calendar_blocks()
	{
		return $this->hasMany(CrmCalendarBlock::class, 'created_by');
	}

	public function crm_leads()
	{
		return $this->hasMany(CrmLead::class, 'created_by');
	}

	public function crm_packages()
	{
		return $this->hasMany(CrmPackage::class, 'updated_by');
	}

	public function crm_projects()
	{
		return $this->hasMany(CrmProject::class, 'owner_id');
	}

	public function crm_proposals()
	{
		return $this->hasMany(CrmProposal::class, 'updated_by');
	}

	public function crm_task_evidences()
	{
		return $this->hasMany(CrmTaskEvidence::class, 'created_by');
	}

	public function crm_tasks()
	{
		return $this->hasMany(CrmTask::class, 'created_by');
	}

	public function crm_ticket_messages()
	{
		return $this->hasMany(CrmTicketMessage::class, 'author_id');
	}

	public function crm_tickets()
	{
		return $this->hasMany(CrmTicket::class, 'reporter_id');
	}

	public function examen_crm_adjuntos()
	{
		return $this->hasMany(ExamenCrmAdjunto::class, 'subido_por');
	}

	public function examen_crm_calendar_blocks()
	{
		return $this->hasMany(ExamenCrmCalendarBlock::class, 'created_by');
	}

	public function examen_crm_detalles()
	{
		return $this->hasMany(ExamenCrmDetalle::class, 'responsable_id');
	}

	public function examen_crm_notas()
	{
		return $this->hasMany(ExamenCrmNota::class, 'autor_id');
	}

	public function examen_crm_tareas()
	{
		return $this->hasMany(ExamenCrmTarea::class, 'created_by');
	}

	public function examen_estado_logs()
	{
		return $this->hasMany(ExamenEstadoLog::class, 'changed_by');
	}

	public function examen_mail_logs()
	{
		return $this->hasMany(ExamenMailLog::class, 'sent_by_user_id');
	}

	public function flowmaker_flows()
	{
		return $this->hasMany(FlowmakerFlow::class, 'updated_by');
	}

	public function imagenes_informes()
	{
		return $this->hasMany(ImagenesInforme::class, 'updated_by');
	}

	public function solicitud_crm_adjuntos()
	{
		return $this->hasMany(SolicitudCrmAdjunto::class, 'subido_por');
	}

	public function solicitud_crm_detalles()
	{
		return $this->hasMany(SolicitudCrmDetalle::class, 'responsable_id');
	}

	public function solicitud_crm_notas()
	{
		return $this->hasMany(SolicitudCrmNota::class, 'autor_id');
	}

	public function solicitud_crm_tareas()
	{
		return $this->hasMany(SolicitudCrmTarea::class, 'created_by');
	}

	public function turnero_resets()
	{
		return $this->hasMany(TurneroReset::class, 'reset_by');
	}

	public function whatsapp_autoresponder_flow_versions()
	{
		return $this->hasMany(WhatsappAutoresponderFlowVersion::class, 'published_by');
	}

	public function whatsapp_autoresponder_flows()
	{
		return $this->hasMany(WhatsappAutoresponderFlow::class, 'updated_by');
	}

	public function whatsapp_message_templates()
	{
		return $this->hasMany(WhatsappMessageTemplate::class, 'updated_by');
	}

	public function whatsapp_template_revisions()
	{
		return $this->hasMany(WhatsappTemplateRevision::class, 'created_by');
	}
}
