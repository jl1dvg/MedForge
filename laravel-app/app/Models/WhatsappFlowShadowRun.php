<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappFlowShadowRun extends Model
{
    protected $table = 'whatsapp_flow_shadow_runs';

    protected $casts = [
        'conversation_id' => 'int',
        'same_match' => 'bool',
        'same_scenario' => 'bool',
        'same_handoff' => 'bool',
        'same_action_types' => 'bool',
        'input_payload' => 'json',
        'parity_payload' => 'json',
        'laravel_payload' => 'json',
        'legacy_payload' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'source',
        'wa_number',
        'conversation_id',
        'inbound_message_id',
        'message_text',
        'same_match',
        'same_scenario',
        'same_handoff',
        'same_action_types',
        'input_payload',
        'parity_payload',
        'laravel_payload',
        'legacy_payload',
    ];
}
