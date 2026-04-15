<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string|null $shortcut
 * @property string $body
 * @property int|null $created_by_user_id
 * @property bool $is_active
 */
class WhatsappQuickReply extends Model
{
    protected $table = 'whatsapp_quick_replies';

    protected $fillable = [
        'title',
        'shortcut',
        'body',
        'created_by_user_id',
        'is_active',
    ];

    protected $casts = [
        'created_by_user_id' => 'integer',
        'is_active' => 'boolean',
    ];
}
