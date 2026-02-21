<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DerivacionesForm
 * 
 * @property int $id
 * @property string $iess_form_id
 * @property string|null $hc_number
 * @property string|null $payer
 * @property string|null $afiliacion_raw
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_registro
 * @property Carbon|null $fecha_vigencia
 * @property string|null $referido
 * @property string|null $diagnostico
 * @property string|null $sede
 * @property string|null $parentesco
 * @property string|null $archivo_derivacion_path
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Collection|DerivacionesInvoice[] $derivaciones_invoices
 * @property Collection|DerivacionesReferralForm[] $derivaciones_referral_forms
 *
 * @package App\Models
 */
class DerivacionesForm extends Model
{
	protected $table = 'derivaciones_forms';

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'fecha_registro' => 'datetime',
		'fecha_vigencia' => 'datetime',
		'source_updated_at' => 'datetime'
	];

	protected $fillable = [
		'iess_form_id',
		'hc_number',
		'payer',
		'afiliacion_raw',
		'fecha_creacion',
		'fecha_registro',
		'fecha_vigencia',
		'referido',
		'diagnostico',
		'sede',
		'parentesco',
		'archivo_derivacion_path',
		'source_updated_at'
	];

	public function derivaciones_invoices()
	{
		return $this->hasMany(DerivacionesInvoice::class, 'form_id');
	}

	public function derivaciones_referral_forms()
	{
		return $this->hasMany(DerivacionesReferralForm::class, 'form_id');
	}
}
