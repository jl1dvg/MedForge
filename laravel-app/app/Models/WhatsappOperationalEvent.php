<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOperationalEvent extends Model
{
    protected $table = 'whatsapp_operational_events';

    protected $casts = [
        'conversation_id' => 'int',
        'handoff_id' => 'int',
        'booking_id' => 'int',
        'reminder_id' => 'int',
        'message_id' => 'int',
        'event_at' => 'datetime',
        'actor_user_id' => 'int',
        'priority_score' => 'float',
        'payload' => 'array',
    ];

    protected $fillable = [
        'conversation_id',
        'handoff_id',
        'booking_id',
        'reminder_id',
        'message_id',
        'event_type',
        'event_group',
        'event_at',
        'actor_type',
        'actor_user_id',
        'producer',
        'bucket',
        'topic',
        'priority_score',
        'wa_number',
        'patient_hc_number',
        'reason',
        'payload',
        'idempotency_key',
    ];
}
