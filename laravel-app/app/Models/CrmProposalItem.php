<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmProposalItem
 * 
 * @property int $id
 * @property int $proposal_id
 * @property int|null $code_id
 * @property int|null $package_id
 * @property string $description
 * @property float $quantity
 * @property float $unit_price
 * @property float $discount_percent
 * @property int $sort_order
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Tarifario2014|null $tarifario2014
 * @property CrmPackage|null $crm_package
 * @property CrmProposal $crm_proposal
 *
 * @package App\Models
 */
class CrmProposalItem extends Model
{
	protected $table = 'crm_proposal_items';

	protected $casts = [
		'proposal_id' => 'int',
		'code_id' => 'int',
		'package_id' => 'int',
		'quantity' => 'float',
		'unit_price' => 'float',
		'discount_percent' => 'float',
		'sort_order' => 'int',
		'metadata' => 'json'
	];

	protected $fillable = [
		'proposal_id',
		'code_id',
		'package_id',
		'description',
		'quantity',
		'unit_price',
		'discount_percent',
		'sort_order',
		'metadata'
	];

	public function tarifario2014()
	{
		return $this->belongsTo(Tarifario2014::class, 'code_id');
	}

	public function crm_package()
	{
		return $this->belongsTo(CrmPackage::class, 'package_id');
	}

	public function crm_proposal()
	{
		return $this->belongsTo(CrmProposal::class, 'proposal_id');
	}
}
