<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

class LegacyExamenesRuntime
{
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        $legacyBasePath = realpath(base_path('..')) ?: base_path('..');

        if (!defined('BASE_PATH')) {
            define('BASE_PATH', $legacyBasePath);
        }
        if (!defined('VIEW_PATH')) {
            define('VIEW_PATH', rtrim($legacyBasePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'views');
        }
        if (!defined('BASE_URL')) {
            define('BASE_URL', '/');
        }

        $prefixes = [
            'Modules\\' => $legacyBasePath . '/modules/',
            'Core\\' => $legacyBasePath . '/core/',
            'Controllers\\' => $legacyBasePath . '/controllers/',
            'Models\\' => $legacyBasePath . '/models/',
            'Helpers\\' => $legacyBasePath . '/helpers/',
            'Services\\' => $legacyBasePath . '/controllers/Services/',
        ];

        spl_autoload_register(static function (string $class) use ($prefixes): void {
            foreach ($prefixes as $prefix => $legacyBaseDir) {
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    continue;
                }

                $relativeClass = substr($class, $len);
                $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

                $paths = [
                    $legacyBaseDir . $relativePath,
                    $legacyBaseDir . strtolower($relativePath),
                ];

                $segments = explode(DIRECTORY_SEPARATOR, $relativePath);
                $fileName = array_pop($segments) ?: '';
                $lowerDirPath = implode(DIRECTORY_SEPARATOR, array_map('strtolower', $segments));
                if ($lowerDirPath !== '') {
                    $paths[] = rtrim($legacyBaseDir . $lowerDirPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;
                }

                foreach ($paths as $path) {
                    if (is_file($path)) {
                        require_once $path;
                        return;
                    }
                }
            }
        }, true, true);

        self::$booted = true;
    }
}
