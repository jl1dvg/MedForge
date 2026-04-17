<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappKnowledgeDocument extends Model
{
    protected $table = 'whatsapp_knowledge_documents';

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'content',
        'status',
        'source_type',
        'source_label',
        'metadata',
        'created_by_user_id',
        'updated_by_user_id',
        'published_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_by_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
        'published_at' => 'datetime',
    ];
}
