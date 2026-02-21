<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class BillingSriDocument
 * 
 * @property int $id
 * @property int $billing_id
 * @property string $estado
 * @property string|null $clave_acceso
 * @property string|null $numero_autorizacion
 * @property string|null $xml_enviado
 * @property string|null $respuesta
 * @property string|null $errores
 * @property int $intentos
 * @property Carbon|null $last_sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property BillingMain $billing_main
 *
 * @package App\Models
 */
class BillingSriDocument extends Model
{
	protected $table = 'billing_sri_documents';

	protected $casts = [
		'billing_id' => 'int',
		'intentos' => 'int',
		'last_sent_at' => 'datetime'
	];

	protected $fillable = [
		'billing_id',
		'estado',
		'clave_acceso',
		'numero_autorizacion',
		'xml_enviado',
		'respuesta',
		'errores',
		'intentos',
		'last_sent_at'
	];

	public function billing_main()
	{
		return $this->belongsTo(BillingMain::class, 'billing_id');
	}
}
