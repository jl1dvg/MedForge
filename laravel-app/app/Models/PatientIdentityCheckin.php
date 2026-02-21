<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PatientIdentityCheckin
 * 
 * @property int $id
 * @property int $certification_id
 * @property float|null $verified_signature_score
 * @property float|null $verified_face_score
 * @property string $verification_result
 * @property array|null $metadata
 * @property int|null $created_by
 * @property Carbon $created_at
 * 
 * @property PatientIdentityCertification $patient_identity_certification
 *
 * @package App\Models
 */
class PatientIdentityCheckin extends Model
{
	protected $table = 'patient_identity_checkins';
	public $timestamps = false;

	protected $casts = [
		'certification_id' => 'int',
		'verified_signature_score' => 'float',
		'verified_face_score' => 'float',
		'metadata' => 'json',
		'created_by' => 'int'
	];

	protected $fillable = [
		'certification_id',
		'verified_signature_score',
		'verified_face_score',
		'verification_result',
		'metadata',
		'created_by'
	];

	public function patient_identity_certification()
	{
		return $this->belongsTo(PatientIdentityCertification::class, 'certification_id');
	}
}
