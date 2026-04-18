<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappAiAgentRun extends Model
{
    protected $table = 'whatsapp_ai_agent_runs';

    protected $fillable = [
        'wa_number',
        'scenario_id',
        'action_index',
        'input_text',
        'filters',
        'matched_documents',
        'response_text',
        'classification',
        'confidence',
        'suggested_handoff',
        'decision',
        'fallback_used',
        'handoff_reasons',
        'scorecard',
        'evaluation',
        'context_before',
        'context_after',
        'source',
    ];

    protected $casts = [
        'action_index' => 'integer',
        'filters' => 'array',
        'matched_documents' => 'array',
        'confidence' => 'float',
        'suggested_handoff' => 'boolean',
        'fallback_used' => 'boolean',
        'handoff_reasons' => 'array',
        'scorecard' => 'array',
        'evaluation' => 'array',
        'context_before' => 'array',
        'context_after' => 'array',
    ];
}
