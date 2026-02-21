<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmLeadLink
 * 
 * @property int $id
 * @property int $lead_id
 * @property string $context_type
 * @property int $context_id
 * @property Carbon|null $created_at
 * 
 * @property CrmLead $crm_lead
 *
 * @package App\Models
 */
class CrmLeadLink extends Model
{
	protected $table = 'crm_lead_links';
	public $timestamps = false;

	protected $casts = [
		'lead_id' => 'int',
		'context_id' => 'int'
	];

	protected $fillable = [
		'lead_id',
		'context_type',
		'context_id'
	];

	public function crm_lead()
	{
		return $this->belongsTo(CrmLead::class, 'lead_id');
	}
}
