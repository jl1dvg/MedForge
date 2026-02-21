<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PalabrasClave
 * 
 * @property int $id
 * @property int $dx_id
 * @property string $palabra
 * 
 * @property Icd10DxOrderCode $icd10_dx_order_code
 *
 * @package App\Models
 */
class PalabrasClave extends Model
{
	protected $table = 'palabras_clave';
	public $timestamps = false;

	protected $casts = [
		'dx_id' => 'int'
	];

	protected $fillable = [
		'dx_id',
		'palabra'
	];

	public function icd10_dx_order_code()
	{
		return $this->belongsTo(Icd10DxOrderCode::class, 'dx_id');
	}
}
