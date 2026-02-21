<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Medicamento
 * 
 * @property int $id
 * @property string $medicamento
 * @property string $via_administracion
 *
 * @package App\Models
 */
class Medicamento extends Model
{
	protected $table = 'medicamentos';
	public $timestamps = false;

	protected $fillable = [
		'medicamento',
		'via_administracion'
	];
}
