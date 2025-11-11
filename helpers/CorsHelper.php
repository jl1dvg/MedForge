<?php

namespace Helpers;

class CorsHelper
{
    /**
     * Configura los encabezados CORS permitiendo únicamente los orígenes definidos
     * en la variable de entorno indicada. Si no se define ningún origen, se permite
     * cualquier petición.
     *
     * @param string|null $envKey Nombre de la variable de entorno con la lista de orígenes permitidos separados por coma.
     * @return bool Devuelve false cuando el origen no está permitido.
     */
    public static function prepare(?string $envKey = null): bool
    {
        $allowedOrigins = self::getAllowedOrigins($envKey);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin !== '' && $allowedOrigins !== null && !in_array($origin, $allowedOrigins, true)) {
            return false;
        }

        if ($origin !== '' && ($allowedOrigins === null || in_array($origin, $allowedOrigins, true))) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: *');
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return true;
    }

    /**
     * @return array<int, string>|null
     */
    private static function getAllowedOrigins(?string $envKey): ?array
    {
        $key = $envKey ?? 'CORS_ALLOWED_ORIGINS';
        $raw = $_ENV[$key] ?? getenv($key) ?? '';

        $parts = array_filter(array_map('trim', preg_split('/\s*,\s*/', $raw) ?: []));

        return $parts !== [] ? $parts : null;
    }
}
