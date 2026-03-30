<?php

namespace App\Modules\Derivaciones\Http\Controllers;

use App\Modules\Derivaciones\Services\DerivacionesParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DerivacionesReadController
{
    private DerivacionesParityService $service;
    private string $projectRoot;
    private string $sigcenterDerivacionesBasePath;

    public function __construct()
    {
        $this->projectRoot = dirname(base_path());
        $this->sigcenterDerivacionesBasePath = rtrim(
            (string) (env('SIGCENTER_DERIVACIONES_BASE_PATH') ?: '/var/www/html/GOOGLE/frontend/web/data'),
            DIRECTORY_SEPARATOR
        );
        $this->service = new DerivacionesParityService(
            DB::connection()->getPdo(),
            $this->projectRoot
        );
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesión expirada',
            ], 401);
        }

        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = max((int) $request->input('length', 25), 1);
        $search = trim((string) $request->input('search.value', ''));
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = (string) $request->input('order.0.dir', 'desc');

        $columnMap = [
            0 => 'fecha_creacion',
            1 => 'cod_derivacion',
            2 => 'form_id',
            3 => 'hc_number',
            4 => 'paciente_nombre',
            5 => 'referido',
            6 => 'fecha_registro',
            7 => 'fecha_vigencia',
            8 => 'archivo',
            9 => 'diagnostico',
            10 => 'sede',
            11 => 'parentesco',
        ];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'fecha_creacion';
        $archivoEndpoint = $this->resolveBaseRoute($request) . '/archivo';

        try {
            $resultado = $this->service->obtenerPaginadas(
                $start,
                $length,
                $search,
                $orderColumn,
                $orderDir,
                $archivoEndpoint
            );

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => $resultado['total'],
                'recordsFiltered' => $resultado['filtrados'],
                'data' => $resultado['datos'],
            ]);
        } catch (\Throwable $e) {
            Log::error('derivaciones.datatable.error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'No se pudo cargar derivaciones',
            ], 500);
        }
    }

    public function archivo(Request $request, int $id): BinaryFileResponse|RedirectResponse|Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $derivacion = $this->service->buscarPorId($id);
        if (!is_array($derivacion)) {
            return response('Derivación no encontrada', 404);
        }

        $rutaRelativa = trim((string) ($derivacion['archivo_derivacion_path'] ?? ''));
        if ($rutaRelativa === '') {
            return response('La derivación no tiene archivo asociado', 404);
        }

        $rutaReal = $this->resolveDerivacionAbsolutePath($rutaRelativa);
        if ($rutaReal === null) {
            $remoteResponse = $this->streamSigcenterRemoteFile($rutaRelativa, [
                'derivacion_id' => $id,
                'archivo_path' => $rutaRelativa,
            ]);
            if ($remoteResponse !== null) {
                return $remoteResponse;
            }

            Log::warning('derivaciones.archivo.not_found', [
                'derivacion_id' => $id,
                'archivo_path' => $rutaRelativa,
            ]);

            return response('Archivo de derivación no encontrado en disco', 404);
        }

        $filename = basename($rutaReal);

        return response()->file($rutaReal, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function archivoPorFormId(Request $request): BinaryFileResponse|RedirectResponse|Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $formId = trim((string) $request->query('form_id', ''));
        if ($formId === '') {
            return response('Parámetro form_id requerido', 422);
        }

        $rutaArchivo = $this->buscarArchivoDerivacionPorFormId($formId);
        if ($rutaArchivo === null) {
            return response('La derivación no tiene archivo asociado', 404);
        }

        $rutaReal = $this->resolveDerivacionAbsolutePath($rutaArchivo);
        if ($rutaReal === null) {
            $remoteResponse = $this->streamSigcenterRemoteFile($rutaArchivo, [
                'form_id' => $formId,
                'archivo_path' => $rutaArchivo,
            ]);
            if ($remoteResponse !== null) {
                return $remoteResponse;
            }

            Log::warning('derivaciones.archivo_by_form.not_found', [
                'form_id' => $formId,
                'archivo_path' => $rutaArchivo,
            ]);

            return response('Archivo de derivación no encontrado en disco', 404);
        }

        $filename = basename($rutaReal);

        return response()->file($rutaReal, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function resolveBaseRoute(Request $request): string
    {
        $path = '/' . ltrim($request->path(), '/');

        return str_starts_with($path, '/v2/') ? '/v2/derivaciones' : '/derivaciones';
    }

    private function buscarArchivoDerivacionPorFormId(string $formId): ?string
    {
        $queries = [
            'SELECT archivo_derivacion_path FROM derivaciones_form_id WHERE form_id = ? AND archivo_derivacion_path IS NOT NULL AND archivo_derivacion_path <> \'\' ORDER BY id DESC LIMIT 1',
            'SELECT archivo_derivacion_path FROM derivaciones_forms WHERE iess_form_id = ? AND archivo_derivacion_path IS NOT NULL AND archivo_derivacion_path <> \'\' ORDER BY id DESC LIMIT 1',
        ];

        foreach ($queries as $sql) {
            try {
                $stmt = DB::connection()->getPdo()->prepare($sql);
                $stmt->execute([$formId]);
                $value = $stmt->fetchColumn();
                if ($value !== false && trim((string) $value) !== '') {
                    return trim((string) $value);
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private function resolveDerivacionAbsolutePath(string $rutaRelativa): ?string
    {
        $ruta = trim(str_replace('\\', '/', $rutaRelativa));
        if ($ruta === '') {
            return null;
        }

        $storageBase = realpath($this->projectRoot . '/storage/derivaciones');
        if (is_string($storageBase)) {
            $rutaNormalizada = ltrim($ruta, '/');
            $rutaAbsoluta = $this->projectRoot . '/' . $rutaNormalizada;
            $rutaReal = realpath($rutaAbsoluta);
            if ($this->isAllowedDerivacionPath($rutaReal, $storageBase)) {
                return $rutaReal;
            }
        }

        $sigcenterBase = realpath($this->sigcenterDerivacionesBasePath);
        if (is_string($sigcenterBase)) {
            $rutaAbsoluta = str_starts_with($ruta, '/')
                ? $ruta
                : $sigcenterBase . '/' . ltrim($ruta, '/');
            $rutaReal = realpath($rutaAbsoluta);
            if ($this->isAllowedDerivacionPath($rutaReal, $sigcenterBase)) {
                return $rutaReal;
            }
        }

        return null;
    }

    private function isAllowedDerivacionPath(string|false $rutaReal, string|false $baseReal): bool
    {
        if (!is_string($rutaReal) || !is_string($baseReal)) {
            return false;
        }

        $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($rutaReal, $basePrefix)) {
            return false;
        }

        return is_file($rutaReal);
    }

    private function streamSigcenterRemoteFile(string $absolutePath, array $context = []): ?Response
    {
        $absolutePath = trim(str_replace('\\', '/', $absolutePath));
        if ($absolutePath === '' || !str_starts_with($absolutePath, $this->sigcenterDerivacionesBasePath . '/')) {
            return null;
        }

        if (!class_exists(\phpseclib3\Net\SFTP::class)) {
            Log::warning('derivaciones.archivo.remote.phpseclib_missing', $context);
            return null;
        }

        $host = trim((string) (env('SIGCENTER_FILES_SSH_HOST') ?: ''));
        $port = (int) (env('SIGCENTER_FILES_SSH_PORT') ?: 22);
        $user = trim((string) (env('SIGCENTER_FILES_SSH_USER') ?: ''));
        $pass = (string) (env('SIGCENTER_FILES_SSH_PASS') ?: '');
        if ($host === '' || $user === '' || $pass === '') {
            Log::warning('derivaciones.archivo.remote.credentials_missing', $context);
            return null;
        }

        try {
            $sftp = new \phpseclib3\Net\SFTP($host, $port, 20);
            if (!$sftp->login($user, $pass)) {
                Log::warning('derivaciones.archivo.remote.login_failed', $context + ['host' => $host, 'port' => $port]);
                return null;
            }

            $contents = $sftp->get($absolutePath);
            if (!is_string($contents) || $contents === '') {
                Log::warning('derivaciones.archivo.remote.read_failed', $context + ['host' => $host, 'path' => $absolutePath]);
                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('derivaciones.archivo.remote.exception', $context + ['error' => $e->getMessage()]);
            return null;
        }

        $filename = basename($absolutePath);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
