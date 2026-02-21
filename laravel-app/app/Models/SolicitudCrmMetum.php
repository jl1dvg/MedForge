<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class SolicitudCrmMetum
 * 
 * @property int $id
 * @property int $solicitud_id
 * @property string $meta_key
 * @property string|null $meta_value
 * @property string|null $meta_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * 
 * @property SolicitudProcedimiento $solicitud_procedimiento
 *
 * @package App\Models
 */
class SolicitudCrmMetum extends Model
{
	protected $table = 'solicitud_crm_meta';

	protected $casts = [
		'solicitud_id' => 'int'
	];

	protected $fillable = [
		'solicitud_id',
		'meta_key',
		'meta_value',
		'meta_type'
	];

	public function solicitud_procedimiento()
	{
		return $this->belongsTo(SolicitudProcedimiento::class, 'solicitud_id');
	}
}
