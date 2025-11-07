<?php

namespace Modules\WhatsApp;

use Modules\WhatsApp\Services\Messenger;
use PDO;

class WhatsAppModule
{
    public static function messenger(PDO $pdo): Messenger
    {
        return new Messenger($pdo);
    }
}
