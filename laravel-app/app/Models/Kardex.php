<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Kardex
 * 
 * @property string $procedimiento_id
 * @property array $medicamentos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property Procedimiento $procedimiento
 *
 * @package App\Models
 */
class Kardex extends Model
{
	protected $table = 'kardex';
	protected $primaryKey = 'procedimiento_id';
	public $incrementing = false;

	protected $casts = [
		'medicamentos' => 'json'
	];

	protected $fillable = [
		'medicamentos'
	];

	public function procedimiento()
	{
		return $this->belongsTo(Procedimiento::class);
	}
}
