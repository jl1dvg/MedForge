<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcCatalogo
 * 
 * @property int $id
 * @property int $categoria_id
 * @property string $raw_text
 * @property string|null $nombre
 * @property string|null $codigo
 * @property string|null $detalle
 * @property int $total
 * @property string $raw_norm
 * @property Carbon|null $created_at
 * 
 * @property ProcCategoria $proc_categoria
 *
 * @package App\Models
 */
class ProcCatalogo extends Model
{
	protected $table = 'proc_catalogo';
	public $timestamps = false;

	protected $casts = [
		'categoria_id' => 'int',
		'total' => 'int'
	];

	protected $fillable = [
		'categoria_id',
		'raw_text',
		'nombre',
		'codigo',
		'detalle',
		'total',
		'raw_norm'
	];

	public function proc_categoria()
	{
		return $this->belongsTo(ProcCategoria::class, 'categoria_id');
	}
}
