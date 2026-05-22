<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappAppointmentReminder extends Model
{
    protected $table = 'whatsapp_appointment_reminders';

    protected $casts = [
        'conversation_id' => 'int',
        'form_id' => 'int',
        'event_at' => 'datetime',
        'payload' => 'json',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'responded_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $fillable = [
        'conversation_id',
        'wa_number',
        'hc_number',
        'form_id',
        'source_type',
        'template_code',
        'reminder_window',
        'dedupe_key',
        'event_at',
        'status',
        'template_message_id',
        'payload',
        'response_value',
        'sent_at',
        'delivered_at',
        'failed_at',
        'responded_at',
        'closed_at',
        'notes',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }
}
