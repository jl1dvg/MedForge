<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $conversation_id
 * @property int|null $message_id
 * @property string|null $wa_number
 * @property string|null $patient_hc_number
 * @property int|null $user_id
 * @property string $event_type
 * @property string $severity
 * @property string|null $summary
 * @property array<string,mixed>|null $payload
 * @property string|null $scenario_id
 * @property string|null $node_id
 * @property string|null $action_type
 * @property array<string,mixed>|null $before_state
 * @property array<string,mixed>|null $after_state
 * @property string|null $error_code
 * @property string|null $error_message
 * @property string|null $meta_request_id
 * @property \Carbon\Carbon $occurred_at
 */
class WhatsappAuditLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'message_id',
        'wa_number',
        'patient_hc_number',
        'user_id',
        'event_type',
        'severity',
        'summary',
        'payload',
        'scenario_id',
        'node_id',
        'action_type',
        'before_state',
        'after_state',
        'error_code',
        'error_message',
        'meta_request_id',
        'occurred_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'before_state' => 'array',
        'after_state'  => 'array',
        'occurred_at'  => 'datetime:Y-m-d H:i:s.v',
    ];
}
