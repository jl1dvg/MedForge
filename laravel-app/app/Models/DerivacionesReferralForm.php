<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesReferralForm
 * 
 * @property int $id
 * @property int $referral_id
 * @property int $form_id
 * @property string|null $status
 * @property Carbon|null $linked_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property DerivacionesForm $derivaciones_form
 * @property DerivacionesReferral $derivaciones_referral
 *
 * @package App\Models
 */
class DerivacionesReferralForm extends Model
{
	protected $table = 'derivaciones_referral_forms';

	protected $casts = [
		'referral_id' => 'int',
		'form_id' => 'int',
		'linked_at' => 'datetime'
	];

	protected $fillable = [
		'referral_id',
		'form_id',
		'status',
		'linked_at',
		'notes'
	];

	public function derivaciones_form()
	{
		return $this->belongsTo(DerivacionesForm::class, 'form_id');
	}

	public function derivaciones_referral()
	{
		return $this->belongsTo(DerivacionesReferral::class, 'referral_id');
	}
}
