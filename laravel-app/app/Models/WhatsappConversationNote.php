<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $conversation_id
 * @property int|null $author_user_id
 * @property string $body
 */
class WhatsappConversationNote extends Model
{
    protected $table = 'whatsapp_conversation_notes';

    protected $fillable = [
        'conversation_id',
        'author_user_id',
        'body',
    ];

    protected $casts = [
        'conversation_id' => 'integer',
        'author_user_id' => 'integer',
    ];
}
