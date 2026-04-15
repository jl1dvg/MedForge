<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $campaign_id
 * @property string $wa_number
 * @property string $status
 */
class WhatsappCampaignDelivery extends Model
{
    protected $table = 'whatsapp_campaign_deliveries';

    protected $fillable = [
        'campaign_id',
        'wa_number',
        'contact_name',
        'status',
        'template_name',
        'payload',
        'executed_at',
        'error_detail',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'payload' => 'array',
        'executed_at' => 'datetime',
    ];
}
