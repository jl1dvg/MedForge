<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $status
 * @property int|null $template_id
 * @property array<int, array<string, mixed>>|null $audience_payload
 * @property bool $dry_run
 */
class WhatsappCampaign extends Model
{
    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'status',
        'template_id',
        'template_name',
        'audience_payload',
        'audience_count',
        'dry_run',
        'scheduled_at',
        'last_executed_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'template_id' => 'integer',
        'audience_payload' => 'array',
        'audience_count' => 'integer',
        'dry_run' => 'boolean',
        'scheduled_at' => 'datetime',
        'last_executed_at' => 'datetime',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];
}
