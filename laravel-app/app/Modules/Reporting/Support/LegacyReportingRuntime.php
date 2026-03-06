<?php

namespace App\Modules\Reporting\Support;

use Dotenv\Dotenv;
use RuntimeException;

class LegacyReportingRuntime
{
    private static bool $bootstrapped = false;
    private static ?string $legacyBasePath = null;

    public static function bootstrap(): string
    {
        if (self::$bootstrapped && is_string(self::$legacyBasePath) && self::$legacyBasePath !== '') {
            return self::$legacyBasePath;
        }

        $legacyBasePath = realpath(base_path('..'));
        if (!is_string($legacyBasePath) || $legacyBasePath === '' || !is_dir($legacyBasePath . '/modules/Reporting')) {
            throw new RuntimeException('No se pudo resolver la ruta base del proyecto legacy.');
        }

        $autoloadPath = $legacyBasePath . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            throw new RuntimeException('No se encontró el autoload de Composer del proyecto legacy.');
        }

        require_once $autoloadPath;
        self::loadLegacyEnv($legacyBasePath);

        self::defineConstant('BASE_PATH', $legacyBasePath);
        self::defineConstant('PUBLIC_PATH', $legacyBasePath . '/public');
        self::defineConstant('CONTROLLER_PATH', $legacyBasePath . '/controllers');
        self::defineConstant('MODEL_PATH', $legacyBasePath . '/models');
        self::defineConstant('HELPER_PATH', $legacyBasePath . '/helpers');
        self::defineConstant('VIEW_PATH', $legacyBasePath . '/views');

        self::$legacyBasePath = $legacyBasePath;
        self::$bootstrapped = true;

        return $legacyBasePath;
    }

    private static function defineConstant(string $name, string $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    private static function loadLegacyEnv(string $legacyBasePath): void
    {
        $envPath = $legacyBasePath . '/.env';
        if (!is_file($envPath) || !class_exists(Dotenv::class)) {
            return;
        }

        try {
            $dotenv = Dotenv::createImmutable($legacyBasePath);
            if (method_exists($dotenv, 'safeLoad')) {
                $dotenv->safeLoad();
                return;
            }

            $dotenv->load();
        } catch (\Throwable) {
            // No bloquear el runtime si el .env legacy no se puede cargar.
        }
    }
}
