<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ProcCategoria
 * 
 * @property int $id
 * @property string $nombre
 * @property string $nombre_norm
 * @property Carbon|null $created_at
 * 
 * @property Collection|ProcCatalogo[] $proc_catalogos
 *
 * @package App\Models
 */
class ProcCategoria extends Model
{
	protected $table = 'proc_categorias';
	public $timestamps = false;

	protected $fillable = [
		'nombre',
		'nombre_norm'
	];

	public function proc_catalogos()
	{
		return $this->hasMany(ProcCatalogo::class, 'categoria_id');
	}
}
