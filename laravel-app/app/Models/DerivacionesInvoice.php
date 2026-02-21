<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesInvoice
 * 
 * @property int $id
 * @property string $invoice_number
 * @property int|null $referral_id
 * @property int|null $form_id
 * @property string|null $hc_number
 * @property float|null $total_amount
 * @property string|null $status
 * @property string $source
 * @property Carbon|null $submitted_at
 * @property Carbon|null $paid_at
 * @property string|null $rejection_reason
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property DerivacionesForm|null $derivaciones_form
 * @property DerivacionesReferral|null $derivaciones_referral
 *
 * @package App\Models
 */
class DerivacionesInvoice extends Model
{
	protected $table = 'derivaciones_invoices';

	protected $casts = [
		'referral_id' => 'int',
		'form_id' => 'int',
		'total_amount' => 'float',
		'submitted_at' => 'datetime',
		'paid_at' => 'datetime',
		'source_updated_at' => 'datetime'
	];

	protected $fillable = [
		'invoice_number',
		'referral_id',
		'form_id',
		'hc_number',
		'total_amount',
		'status',
		'source',
		'submitted_at',
		'paid_at',
		'rejection_reason',
		'source_updated_at'
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
