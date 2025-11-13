<?php

namespace Helpers;

use DateTimeImmutable;
use Throwable;

class JsonLogger
{
    private const DEFAULT_CHANNEL = 'application';

    /**
     * Registra un mensaje en un archivo JSONL dentro de storage/logs.
     */
    public static function log(string $channel, string $message, ?Throwable $exception = null, array $context = []): void
    {
        try {
            $entry = [
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
                'channel' => $channel,
                'message' => $message,
            ];

            if ($exception !== null) {
                $entry['exception'] = [
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }

            if ($context !== []) {
                $entry['context'] = self::normalizeContext($context);
            }

            $logDirectory = dirname(__DIR__) . '/storage/logs';
            if (!is_dir($logDirectory) && !@mkdir($logDirectory, 0775, true) && !is_dir($logDirectory)) {
                throw new \RuntimeException('No fue posible crear el directorio de logs: ' . $logDirectory);
            }

            $filePath = sprintf('%s/%s.jsonl', $logDirectory, self::sanitizeChannel($channel));
            $payload = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new \RuntimeException('No fue posible serializar la entrada de log.');
            }

            file_put_contents($filePath, $payload . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $loggerException) {
            error_log('[JsonLogger] ' . $loggerException->getMessage());
        }
    }

    private static function sanitizeChannel(string $channel): string
    {
        $normalized = strtolower(trim($channel));
        $normalized = preg_replace('/[^a-z0-9\-_]+/', '-', $normalized ?? '');

        return $normalized !== '' ? $normalized : self::DEFAULT_CHANNEL;
    }

    private static function normalizeContext(array $context): array
    {
        $normalizer = static function ($value) use (&$normalizer) {
            if ($value instanceof Throwable) {
                return [
                    'type' => get_class($value),
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                ];
            }

            if (is_array($value)) {
                return array_map($normalizer, $value);
            }

            if (is_object($value)) {
                return method_exists($value, '__toString') ? (string) $value : get_class($value);
            }

            return $value;
        };

        return array_map($normalizer, $context);
    }
}
