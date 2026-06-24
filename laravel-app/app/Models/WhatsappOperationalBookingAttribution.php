<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOperationalBookingAttribution extends Model
{
    protected $table = 'whatsapp_operational_booking_attributions';

    protected $casts = [
        'booking_id' => 'int',
        'booking_conversation_id' => 'int',
        'attributed_conversation_id' => 'int',
        'handoff_id' => 'int',
        'event_id' => 'int',
        'event_at' => 'datetime',
        'booking_at' => 'datetime',
    ];

    protected $fillable = [
        'booking_id',
        'booking_conversation_id',
        'attributed_conversation_id',
        'handoff_id',
        'event_id',
        'event_type',
        'attribution_method',
        'confidence',
        'event_at',
        'booking_at',
    ];
}
