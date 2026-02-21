<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesReferral
 * 
 * @property int $id
 * @property string $referral_code
 * @property string $source
 * @property string|null $status
 * @property string|null $issued_by
 * @property string|null $priority
 * @property string|null $service_type
 * @property Carbon|null $issued_at
 * @property Carbon|null $valid_until
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|DerivacionesInvoice[] $derivaciones_invoices
 * @property Collection|DerivacionesReferralForm[] $derivaciones_referral_forms
 *
 * @package App\Models
 */
class DerivacionesReferral extends Model
{
	protected $table = 'derivaciones_referrals';

	protected $casts = [
		'issued_at' => 'datetime',
		'valid_until' => 'datetime',
		'source_updated_at' => 'datetime'
	];

	protected $fillable = [
		'referral_code',
		'source',
		'status',
		'issued_by',
		'priority',
		'service_type',
		'issued_at',
		'valid_until',
		'source_updated_at'
	];

	public function derivaciones_invoices()
	{
		return $this->hasMany(DerivacionesInvoice::class, 'referral_id');
	}

	public function derivaciones_referral_forms()
	{
		return $this->hasMany(DerivacionesReferralForm::class, 'referral_id');
	}
}
