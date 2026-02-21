<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmPackage
 * 
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property array|null $tags
 * @property int $total_items
 * @property float $total_amount
 * @property bool $active
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon $created_at
 * @property Carbon|null $updated_at
 * 
 * @property User|null $user
 * @property Collection|CrmPackageItem[] $crm_package_items
 * @property Collection|CrmProposalItem[] $crm_proposal_items
 *
 * @package App\Models
 */
class CrmPackage extends Model
{
	protected $table = 'crm_packages';

	protected $casts = [
		'tags' => 'json',
		'total_items' => 'int',
		'total_amount' => 'float',
		'active' => 'bool',
		'created_by' => 'int',
		'updated_by' => 'int'
	];

	protected $fillable = [
		'slug',
		'name',
		'description',
		'category',
		'tags',
		'total_items',
		'total_amount',
		'active',
		'created_by',
		'updated_by'
	];

	public function user()
	{
		return $this->belongsTo(User::class, 'updated_by');
	}

	public function crm_package_items()
	{
		return $this->hasMany(CrmPackageItem::class, 'package_id');
	}

	public function crm_proposal_items()
	{
		return $this->hasMany(CrmProposalItem::class, 'package_id');
	}
}
