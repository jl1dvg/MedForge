<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Tarifario2014
 * 
 * @property int $id
 * @property string|null $codigo
 * @property string|null $descripcion
 * @property float|null $uvr1
 * @property float|null $uvr2
 * @property float|null $uvr3
 * @property float|null $valor_facturar_nivel1
 * @property float|null $valor_facturar_nivel2
 * @property float|null $valor_facturar_nivel3
 * @property float|null $anestesia_nivel1
 * @property float|null $anestesia_nivel2
 * @property float|null $anestesia_nivel3
 * @property string|null $code_type
 * @property string|null $modifier
 * @property string|null $superbill
 * @property bool $active
 * @property bool $reportable
 * @property bool $financial_reporting
 * @property string|null $revenue_code
 * @property string|null $short_description
 * 
 * @property Collection|CodeExternalMap[] $code_external_maps
 * @property Collection|CodeTaxRate[] $code_tax_rates
 * @property Collection|CrmPackageItem[] $crm_package_items
 * @property Collection|CrmProposalItem[] $crm_proposal_items
 * @property Collection|Price[] $prices
 * @property Collection|RelatedCode[] $related_codes
 *
 * @package App\Models
 */
class Tarifario2014 extends Model
{
	protected $table = 'tarifario_2014';
	public $timestamps = false;

	protected $casts = [
		'uvr1' => 'float',
		'uvr2' => 'float',
		'uvr3' => 'float',
		'valor_facturar_nivel1' => 'float',
		'valor_facturar_nivel2' => 'float',
		'valor_facturar_nivel3' => 'float',
		'anestesia_nivel1' => 'float',
		'anestesia_nivel2' => 'float',
		'anestesia_nivel3' => 'float',
		'active' => 'bool',
		'reportable' => 'bool',
		'financial_reporting' => 'bool'
	];

	protected $fillable = [
		'codigo',
		'descripcion',
		'uvr1',
		'uvr2',
		'uvr3',
		'valor_facturar_nivel1',
		'valor_facturar_nivel2',
		'valor_facturar_nivel3',
		'anestesia_nivel1',
		'anestesia_nivel2',
		'anestesia_nivel3',
		'code_type',
		'modifier',
		'superbill',
		'active',
		'reportable',
		'financial_reporting',
		'revenue_code',
		'short_description'
	];

	public function code_external_maps()
	{
		return $this->hasMany(CodeExternalMap::class, 'code_id');
	}

	public function code_tax_rates()
	{
		return $this->hasMany(CodeTaxRate::class, 'code_id');
	}

	public function crm_package_items()
	{
		return $this->hasMany(CrmPackageItem::class, 'code_id');
	}

	public function crm_proposal_items()
	{
		return $this->hasMany(CrmProposalItem::class, 'code_id');
	}

	public function prices()
	{
		return $this->hasMany(Price::class, 'code_id');
	}

	public function related_codes()
	{
		return $this->hasMany(RelatedCode::class, 'related_code_id');
	}
}
