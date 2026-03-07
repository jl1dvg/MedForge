<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Services;

use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class LegacyExamenesBridge
{
    private static bool $runtimeReady = false;

    /**
     * @param array<int, mixed> $args
     * @return array{status:int,headers:array<string,string>,body:string}
     */
    public function dispatch(Request $request, string $method, array $args = []): array
    {
        $this->ensureLegacyRuntime();

        if (!class_exists(\Controllers\ExamenController::class)) {
            throw new RuntimeException('No se pudo cargar Controllers\\ExamenController.');
        }

        $originalGlobals = $this->captureGlobals();
        $originalStatus = http_response_code();

        try {
            $this->hydrateLegacySuperglobals($request);
            http_response_code(200);

            ob_start();
            $controller = new \Controllers\ExamenController(DB::connection()->getPdo());
            $controller->{$method}(...$args);
            $body = (string) ob_get_clean();

            $status = http_response_code();
            if (!is_int($status) || $status < 100) {
                $status = 200;
            }

            $headers = $this->extractHeaders(headers_list());
            if (function_exists('header_remove')) {
                header_remove();
            }

            return [
                'status' => $status,
                'headers' => $headers,
                'body' => $body,
            ];
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (function_exists('header_remove')) {
                header_remove();
            }

            throw $e;
        } finally {
            $this->restoreGlobals($originalGlobals);
            if (is_int($originalStatus) && $originalStatus >= 100) {
                http_response_code($originalStatus);
            }
        }
    }

    private function ensureLegacyRuntime(): void
    {
        if (self::$runtimeReady) {
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

        self::$runtimeReady = true;
    }

    /**
     * @return array{_GET:array<string,mixed>,_POST:array<string,mixed>,_FILES:array<string,mixed>,_REQUEST:array<string,mixed>,_SERVER:array<string,mixed>,_SESSION:array<string,mixed>|null}
     */
    private function captureGlobals(): array
    {
        return [
            '_GET' => $_GET ?? [],
            '_POST' => $_POST ?? [],
            '_FILES' => $_FILES ?? [],
            '_REQUEST' => $_REQUEST ?? [],
            '_SERVER' => $_SERVER ?? [],
            '_SESSION' => $_SESSION ?? null,
        ];
    }

    /**
     * @param array{_GET:array<string,mixed>,_POST:array<string,mixed>,_FILES:array<string,mixed>,_REQUEST:array<string,mixed>,_SERVER:array<string,mixed>,_SESSION:array<string,mixed>|null} $snapshot
     */
    private function restoreGlobals(array $snapshot): void
    {
        $_GET = $snapshot['_GET'];
        $_POST = $snapshot['_POST'];
        $_FILES = $snapshot['_FILES'];
        $_REQUEST = $snapshot['_REQUEST'];
        $_SERVER = $snapshot['_SERVER'];

        if (is_array($snapshot['_SESSION'])) {
            $_SESSION = $snapshot['_SESSION'];
            return;
        }

        unset($_SESSION);
    }

    private function hydrateLegacySuperglobals(Request $request): void
    {
        $legacySession = LegacySessionAuth::readSession($request);

        $_GET = $request->query->all();
        $_POST = $request->request->all();
        $_REQUEST = array_merge($_GET, $_POST);
        $_FILES = $this->normalizeFiles($request->allFiles());
        $_SESSION = is_array($legacySession) ? $legacySession : [];

        $_SERVER['REQUEST_METHOD'] = strtoupper((string) $request->getMethod());
        $_SERVER['QUERY_STRING'] = (string) ($request->getQueryString() ?? '');

        $contentType = trim((string) $request->headers->get('Content-Type', ''));
        if ($contentType !== '') {
            $_SERVER['CONTENT_TYPE'] = $contentType;
        }
    }

    /**
     * @param array<string,mixed> $files
     * @return array<string,mixed>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFile) {
                $normalized[$key] = $this->normalizeUploadedFile($value);
                continue;
            }

            if (is_array($value)) {
                $normalized[$key] = $this->normalizeFiles($value);
            }
        }

        return $normalized;
    }

    /**
     * @return array{name:string,type:string,tmp_name:string,error:int,size:int}
     */
    private function normalizeUploadedFile(UploadedFile $file): array
    {
        return [
            'name' => $file->getClientOriginalName(),
            'type' => (string) ($file->getClientMimeType() ?? 'application/octet-stream'),
            'tmp_name' => $file->getPathname(),
            'error' => (int) $file->getError(),
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * @param array<int,string> $rawHeaders
     * @return array<string,string>
     */
    private function extractHeaders(array $rawHeaders): array
    {
        $headers = [];

        foreach ($rawHeaders as $headerLine) {
            $separatorPos = strpos($headerLine, ':');
            if ($separatorPos === false) {
                continue;
            }

            $name = trim(substr($headerLine, 0, $separatorPos));
            $value = trim(substr($headerLine, $separatorPos + 1));
            if ($name === '' || strcasecmp($name, 'Set-Cookie') === 0) {
                continue;
            }

            if (isset($headers[$name])) {
                $headers[$name] .= ', ' . $value;
            } else {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
