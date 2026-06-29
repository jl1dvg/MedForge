<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappOperationalSnapshot extends Model
{
    protected $table = 'whatsapp_operational_snapshots';

    protected $casts = [
        'snapshot_date' => 'date',
        'payload' => 'array',
        'generated_at' => 'datetime',
    ];

    protected $fillable = [
        'snapshot_date',
        'payload',
        'hot_open_total',
        'hot_open_unassigned',
        'hot_open_assigned',
        'hot_open_booked',
        'hot_needs_template_total',
        'hot_needs_template_booked',
        'rescue_total',
        'rescue_booked',
        'backlog_total',
        'lost_total',
        'rescued_bookings',
        'autoassigned_bookings',
        'reminder_confirmations',
        'reminder_failures',
        'generated_at',
    ];
}
