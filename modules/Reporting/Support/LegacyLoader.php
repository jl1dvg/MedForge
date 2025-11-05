<?php

declare(strict_types=1);

if (!function_exists('reporting_module_path')) {
    /**
     * Attempt to resolve the absolute directory of the Reporting module.
     */
    function reporting_module_path(): ?string
    {
        $candidates = [];

        if (defined('BASE_PATH')) {
            $candidates[] = rtrim(BASE_PATH, DIRECTORY_SEPARATOR) . '/modules/Reporting';
        }

        if (defined('PUBLIC_PATH')) {
            $public = rtrim(PUBLIC_PATH, DIRECTORY_SEPARATOR);
            $candidates[] = $public . '/modules/Reporting';
            $candidates[] = $public . '/../modules/Reporting';
        }

        $candidates[] = dirname(__DIR__); // modules/Reporting
        $candidates[] = dirname(__DIR__, 2) . '/Reporting';
        $candidates[] = dirname(__DIR__, 3) . '/modules/Reporting';

        $visited = [];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalized = rtrim(str_replace('\\', '/', $candidate), '/');

            if ($normalized === '' || isset($visited[$normalized])) {
                continue;
            }

            $visited[$normalized] = true;

            $real = realpath($normalized) ?: $normalized;

            if (is_dir($real) && is_file($real . '/Controllers/ReportController.php')) {
                return $real;
            }
        }

        return null;
    }
}

if (!function_exists('reporting_require_module_file')) {
    /**
     * Require a file relative to the Reporting module directory if it exists.
     */
    function reporting_require_module_file(string $relativePath): void
    {
        $modulePath = reporting_module_path();

        if ($modulePath === null) {
            return;
        }

        $clean = ltrim(str_replace(['\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR], '/', $relativePath), '/');
        $candidate = $modulePath . '/' . $clean;

        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }

        $lowerCandidate = $modulePath . '/' . strtolower($clean);

        if (is_file($lowerCandidate)) {
            require_once $lowerCandidate;
        }
    }
}

if (!function_exists('reporting_bootstrap_legacy')) {
    /**
     * Ensure the Reporting module classes are available in legacy contexts.
     */
    function reporting_bootstrap_legacy(): void
    {
        reporting_require_module_file('Services/ReportService.php');
        reporting_require_module_file('Controllers/ReportController.php');
    }
}

