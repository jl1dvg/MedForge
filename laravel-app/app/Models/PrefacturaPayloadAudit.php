<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PrefacturaPayloadAudit
 * 
 * @property int $id
 * @property int|null $prefactura_id
 * @property string|null $hc_number
 * @property string|null $form_id
 * @property string $source
 * @property string $payload_hash
 * @property array $payload_json
 * @property Carbon $received_at
 * 
 * @property PrefacturaPaciente|null $prefactura_paciente
 *
 * @package App\Models
 */
class PrefacturaPayloadAudit extends Model
{
	protected $table = 'prefactura_payload_audit';
	public $timestamps = false;

	protected $casts = [
		'prefactura_id' => 'int',
		'payload_json' => 'json',
		'received_at' => 'datetime'
	];

	protected $fillable = [
		'prefactura_id',
		'hc_number',
		'form_id',
		'source',
		'payload_hash',
		'payload_json',
		'received_at'
	];

	public function prefactura_paciente()
	{
		return $this->belongsTo(PrefacturaPaciente::class, 'prefactura_id');
	}
}
