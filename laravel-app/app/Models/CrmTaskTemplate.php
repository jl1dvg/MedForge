<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class CrmTaskTemplate
 * 
 * @property int $id
 * @property int $company_id
 * @property string $task_type
 * @property string $whatsapp_template
 * @property array|null $variables
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class CrmTaskTemplate extends Model
{
	protected $table = 'crm_task_templates';

	protected $casts = [
		'company_id' => 'int',
		'variables' => 'json'
	];

	protected $fillable = [
		'company_id',
		'task_type',
		'whatsapp_template',
		'variables'
	];
}
