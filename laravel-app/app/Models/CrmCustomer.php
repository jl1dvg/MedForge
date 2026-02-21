<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmCustomer
 * 
 * @property int $id
 * @property string $type
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $document
 * @property string|null $gender
 * @property Carbon|null $birthdate
 * @property string|null $city
 * @property string|null $address
 * @property string|null $marital_status
 * @property string|null $affiliation
 * @property string|null $nationality
 * @property string|null $workplace
 * @property string $source
 * @property string|null $external_ref
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property string|null $first_name
 * @property string|null $last_name
 * 
 * @property Collection|CrmLead[] $crm_leads
 * @property Collection|CrmProject[] $crm_projects
 * @property Collection|CrmProposal[] $crm_proposals
 *
 * @package App\Models
 */
class CrmCustomer extends Model
{
	protected $table = 'crm_customers';

	protected $casts = [
		'birthdate' => 'datetime'
	];

	protected $fillable = [
		'type',
		'name',
		'email',
		'phone',
		'document',
		'gender',
		'birthdate',
		'city',
		'address',
		'marital_status',
		'affiliation',
		'nationality',
		'workplace',
		'source',
		'external_ref',
		'first_name',
		'last_name'
	];

	public function crm_leads()
	{
		return $this->hasMany(CrmLead::class, 'customer_id');
	}

	public function crm_projects()
	{
		return $this->hasMany(CrmProject::class, 'customer_id');
	}

	public function crm_proposals()
	{
		return $this->hasMany(CrmProposal::class, 'customer_id');
	}
}
