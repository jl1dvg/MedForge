<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappConversationAttribution extends Model
{
    protected $table = 'whatsapp_conversation_attributions';

    protected $casts = [
        'conversation_id' => 'int',
        'first_message_id' => 'int',
        'first_inbound_message_id' => 'int',
        'last_clinical_touch_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'meta' => 'json',
    ];

    protected $fillable = [
        'conversation_id',
        'first_message_id',
        'first_inbound_message_id',
        'source_category',
        'source_type',
        'source_id',
        'source_url',
        'media_type',
        'headline',
        'body',
        'video_url',
        'thumbnail_url',
        'ctwa_clid',
        'welcome_message_text',
        'profile_name',
        'initial_intent',
        'conversation_type',
        'patient_segment',
        'patient_hc_number',
        'last_clinical_touch_at',
        'first_seen_at',
        'last_synced_at',
        'meta',
    ];

    public function conversation()
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }
}
