<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmProposal
 * 
 * @property int $id
 * @property string $proposal_number
 * @property int $proposal_year
 * @property int $sequence
 * @property int|null $lead_id
 * @property int|null $customer_id
 * @property string $title
 * @property string $status
 * @property string $currency
 * @property float $subtotal
 * @property float $discount_total
 * @property float $tax_rate
 * @property float $tax_total
 * @property float $total
 * @property Carbon|null $valid_until
 * @property string|null $notes
 * @property string|null $terms
 * @property array|null $packages_snapshot
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $rejected_at
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property CrmCustomer|null $crm_customer
 * @property CrmLead|null $crm_lead
 * @property Collection|CrmProposalItem[] $crm_proposal_items
 *
 * @package App\Models
 */
class CrmProposal extends Model
{
	protected $table = 'crm_proposals';

	protected $casts = [
		'proposal_year' => 'int',
		'sequence' => 'int',
		'lead_id' => 'int',
		'customer_id' => 'int',
		'subtotal' => 'float',
		'discount_total' => 'float',
		'tax_rate' => 'float',
		'tax_total' => 'float',
		'total' => 'float',
		'valid_until' => 'datetime',
		'packages_snapshot' => 'json',
		'created_by' => 'int',
		'updated_by' => 'int',
		'sent_at' => 'datetime',
		'accepted_at' => 'datetime',
		'rejected_at' => 'datetime'
	];

	protected $fillable = [
		'proposal_number',
		'proposal_year',
		'sequence',
		'lead_id',
		'customer_id',
		'title',
		'status',
		'currency',
		'subtotal',
		'discount_total',
		'tax_rate',
		'tax_total',
		'total',
		'valid_until',
		'notes',
		'terms',
		'packages_snapshot',
		'created_by',
		'updated_by',
		'sent_at',
		'accepted_at',
		'rejected_at'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function crm_customer()
	{
		return $this->belongsTo(CrmCustomer::class, 'customer_id');
	}

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class, 'lead_id');
	}

	public function crm_proposal_items()
	{
		return $this->hasMany(CrmProposalItem::class, 'proposal_id');
	}
}
