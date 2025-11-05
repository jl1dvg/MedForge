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
            $base = rtrim(BASE_PATH, DIRECTORY_SEPARATOR);
            $candidates[] = $base . '/modules/Reporting';
            $candidates[] = $base . '/modules/reporting';
        }

        if (defined('PUBLIC_PATH')) {
            $public = rtrim(PUBLIC_PATH, DIRECTORY_SEPARATOR);
            $candidates[] = $public . '/modules/Reporting';
            $candidates[] = $public . '/../modules/Reporting';
            $candidates[] = $public . '/modules/reporting';
            $candidates[] = $public . '/../modules/reporting';
        }

        $candidates[] = dirname(__DIR__); // modules/Reporting
        $candidates[] = dirname(__DIR__, 2) . '/Reporting';
        $candidates[] = dirname(__DIR__, 3) . '/modules/Reporting';
        $candidates[] = dirname(__DIR__, 2) . '/reporting';
        $candidates[] = dirname(__DIR__, 3) . '/modules/reporting';

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

            if (is_dir($real) && is_dir($real . '/Controllers')) {
                return $real;
            }
        }

        return null;
    }
}

if (!function_exists('reporting_resolve_module_path')) {
    /**
     * Resolve a relative path inside the Reporting module, handling case differences.
     */
    function reporting_resolve_module_path(string $relativePath): ?string
    {
        $modulePath = reporting_module_path();

        if ($modulePath === null) {
            return null;
        }

        $clean = trim(str_replace(['\\', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR], '/', $relativePath), '/');

        if ($clean === '') {
            return $modulePath;
        }

        $segments = explode('/', $clean);
        $current = $modulePath;

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            $direct = $current . '/' . $segment;
            if (file_exists($direct)) {
                $current = $direct;
                continue;
            }

            $candidates = glob($current . '/*', GLOB_NOSORT) ?: [];
            $match = null;

            foreach ($candidates as $candidate) {
                if (strcasecmp(basename($candidate), $segment) === 0) {
                    $match = $candidate;
                    break;
                }
            }

            if ($match === null) {
                return null;
            }

            $current = $match;
        }

        return $current;
    }
}

if (!function_exists('reporting_require_module_file')) {
    /**
     * Require (via include_once) a file relative to the Reporting module directory if it exists.
     */
    function reporting_require_module_file(string $relativePath): void
    {
        $resolved = reporting_resolve_module_path($relativePath);

        if ($resolved === null || !is_file($resolved)) {
            return;
        }

        include_once $resolved;
    }
}

if (!function_exists('reporting_bootstrap_legacy')) {
    /**
     * Ensure the Reporting module classes are available in legacy contexts.
     */
    function reporting_bootstrap_legacy(): void
    {
        if (!class_exists(\Modules\Reporting\Services\ReportService::class, false)) {
            reporting_require_module_file('Services/ReportService.php');
        }

        if (!class_exists(\Modules\Reporting\Support\RenderContext::class, false)) {
            reporting_require_module_file('Support/RenderContext.php');
        }

        if (!class_exists(\Modules\Reporting\Controllers\ReportController::class, false)) {
            reporting_require_module_file('Controllers/ReportController.php');
        }
    }
}

