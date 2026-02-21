<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class WhatsappContactConsent
 * 
 * @property int $id
 * @property string $wa_number
 * @property string $cedula
 * @property string|null $patient_hc_number
 * @property string|null $patient_full_name
 * @property string $consent_status
 * @property string $consent_source
 * @property Carbon|null $consent_asked_at
 * @property Carbon|null $consent_responded_at
 * @property array|null $extra_payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class WhatsappContactConsent extends Model
{
	protected $table = 'whatsapp_contact_consent';

	protected $casts = [
		'consent_asked_at' => 'datetime',
		'consent_responded_at' => 'datetime',
		'extra_payload' => 'json'
	];

	protected $fillable = [
		'wa_number',
		'cedula',
		'patient_hc_number',
		'patient_full_name',
		'consent_status',
		'consent_source',
		'consent_asked_at',
		'consent_responded_at',
		'extra_payload'
	];
}
