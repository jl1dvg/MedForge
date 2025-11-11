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
    public static function prepare(?string $envKey = null, array $fallbackAllowed = []): bool
    {
        $allowedOrigins = self::getAllowedOrigins($envKey, $fallbackAllowed);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        $isChromeExtension = $origin !== '' && str_starts_with($origin, 'chrome-extension://');

        if (
            $origin !== ''
            && !$isChromeExtension
            && $allowedOrigins !== null
            && !self::originAllowed($origin, $allowedOrigins)
        ) {
            return false;
        }

        if ($origin !== '' && ($isChromeExtension || $allowedOrigins === null || self::originAllowed($origin, $allowedOrigins))) {
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
    private static function getAllowedOrigins(?string $envKey, array $fallbackAllowed): ?array
    {
        $key = $envKey ?? 'CORS_ALLOWED_ORIGINS';
        $raw = $_ENV[$key] ?? getenv($key) ?? '';

        $parts = array_filter(array_map('trim', preg_split('/\s*,\s*/', $raw) ?: []));
        if ($fallbackAllowed !== []) {
            $parts = array_merge($parts, $fallbackAllowed);
        }

        $parts = array_values(array_unique(array_filter($parts, static fn($origin) => $origin !== '')));

        return $parts !== [] ? $parts : null;
    }

    /**
     * @param array<int, string> $allowedOrigins
     */
    private static function originAllowed(string $origin, array $allowedOrigins): bool
    {
        foreach ($allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return true;
            }

            if (str_contains($allowed, '*')) {
                $pattern = '#^' . str_replace(['*', '#'], ['.*', '\#'], preg_quote($allowed, '#')) . '$#i';
                if (preg_match($pattern, $origin) === 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
