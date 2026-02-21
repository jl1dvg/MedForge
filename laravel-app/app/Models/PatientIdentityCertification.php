<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PatientIdentityCertification
 * 
 * @property int $id
 * @property string $patient_id
 * @property string $document_number
 * @property string $document_type
 * @property string|null $signature_path
 * @property array|null $signature_template
 * @property string|null $document_signature_path
 * @property string|null $document_front_path
 * @property string|null $document_back_path
 * @property string|null $face_image_path
 * @property array|null $face_template
 * @property string $status
 * @property Carbon|null $expired_at
 * @property Carbon|null $last_verification_at
 * @property string|null $last_verification_result
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Collection|PatientIdentityCheckin[] $patient_identity_checkins
 *
 * @package App\Models
 */
class PatientIdentityCertification extends Model
{
	protected $table = 'patient_identity_certifications';

	protected $casts = [
		'signature_template' => 'json',
		'face_template' => 'json',
		'expired_at' => 'datetime',
		'last_verification_at' => 'datetime',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'patient_id',
		'document_number',
		'document_type',
		'signature_path',
		'signature_template',
		'document_signature_path',
		'document_front_path',
		'document_back_path',
		'face_image_path',
		'face_template',
		'status',
		'expired_at',
		'last_verification_at',
		'last_verification_result',
		'created_by',
		'updated_by'
	];

	public function patient_identity_checkins()
	{
		return $this->hasMany(PatientIdentityCheckin::class, 'certification_id');
	}
}
