<?php

namespace Core;

use PDO;

class ModuleLoader
{
    public static function register(Router $router, PDO $pdo, string $modulesPath): void
    {
        if (!is_dir($modulesPath)) {
            return;
        }

        $directories = glob($modulesPath . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $moduleDir) {
            $bootstrapFile = $moduleDir . '/index.php';
            if (is_file($bootstrapFile)) {
                require_once $bootstrapFile;
            }

            $routesFile = $moduleDir . '/routes.php';
            if (!is_file($routesFile)) {
                continue;
            }

            $definition = (static function (string $routesFile, Router $router, PDO $pdo) {
                return require $routesFile;
            })($routesFile, $router, $pdo);

            if (is_callable($definition)) {
                $definition($router, $pdo);
            }
        }
    }
}
