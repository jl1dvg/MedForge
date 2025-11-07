<?php

namespace Modules\WhatsApp\Support;

class MessageSanitizer
{
    public const MAX_MESSAGE_LENGTH = 4096;

    public static function sanitize(string $message): string
    {
        $message = str_replace("\r", '', $message);
        $message = trim($message);
        if ($message === '') {
            return '';
        }

        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            $message = mb_substr($message, 0, self::MAX_MESSAGE_LENGTH - 1) . '…';
        }

        return $message;
    }
}
