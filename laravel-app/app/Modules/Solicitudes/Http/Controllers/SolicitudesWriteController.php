<?php

declare(strict_types=1);

namespace App\Modules\Solicitudes\Http\Controllers;

use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\Solicitudes\Services\SolicitudesReadParityService;
use App\Modules\Solicitudes\Services\SolicitudesWriteParityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use RuntimeException;
use Throwable;

class SolicitudesWriteController
{
    private SolicitudesWriteParityService $service;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $readService = new SolicitudesReadParityService();
        $this->service = new SolicitudesWriteParityService($pdo, $readService);
    }

    public function apiEstadoGet(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $hcNumber = trim((string) ($request->query('hcNumber', $request->query('hc_number', ''))));
        if ($hcNumber === '') {
            return response()->json(['success' => false, 'message' => 'Parámetro hcNumber requerido'], 400)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->apiEstadoGet($hcNumber);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.api_estado_get.error', [
                'request_id' => $requestId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la solicitud',
                'error' => $e->getMessage(),
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function apiEstadoPost(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);

        try {
            $result = $this->service->apiEstadoPost($payload);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.api_estado_post.error', [
                'request_id' => $requestId,
                'payload_keys' => array_keys($payload),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la solicitud',
                'error' => $e->getMessage(),
            ], 500)->header('X-Request-Id', $requestId);
        }

        $status = (is_array($result) && (($result['success'] ?? false) === false)) ? 422 : 200;

        return response()->json($result, $status)->header('X-Request-Id', $requestId);
    }

    public function actualizarEstado(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $formId = isset($payload['form_id']) ? (int) $payload['form_id'] : 0;
        $estado = trim((string) ($payload['estado'] ?? ''));
        $nota = isset($payload['nota']) ? trim((string) $payload['nota']) : null;
        $completado = isset($payload['completado']) ? (bool) $payload['completado'] : true;
        $force = isset($payload['force']) ? (bool) $payload['force'] : false;

        try {
            $result = $this->service->actualizarEstado(
                $id,
                $formId,
                $estado,
                $completado,
                $force,
                LegacySessionAuth::userId($request),
                $nota,
            );

            Log::info('solicitudes.write.actualizar_estado', [
                'request_id' => $requestId,
                'id' => $id,
                'form_id' => $formId,
                'estado' => $estado,
                'kanban_estado' => $result['kanban_estado'] ?? null,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            $errorRef = bin2hex(random_bytes(6));
            Log::error('solicitudes.write.actualizar_estado.error', [
                'request_id' => $requestId,
                'id' => $id,
                'form_id' => $formId,
                'estado' => $estado,
                'error_ref' => $errorRef,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno (ref: ' . $errorRef . ')',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true] + $result)->header('X-Request-Id', $requestId);
    }

    public function turneroLlamar(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $id = isset($payload['id']) ? (int) $payload['id'] : null;
        $turno = isset($payload['turno']) ? (int) $payload['turno'] : null;
        $estado = trim((string) ($payload['estado'] ?? 'Llamado'));

        if (($id ?? 0) <= 0 && ($turno ?? 0) <= 0) {
            return response()->json(['success' => false, 'error' => 'Debe especificar un ID o número de turno'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $registro = $this->service->turneroLlamar($id, $turno, $estado);
            if ($registro === null) {
                return response()->json(['success' => false, 'error' => 'No se encontró la solicitud indicada'], 404)
                    ->header('X-Request-Id', $requestId);
            }

            $nombre = trim((string) ($registro['full_name'] ?? ''));
            $registro['full_name'] = $nombre !== '' ? $nombre : 'Paciente sin nombre';

            Log::info('solicitudes.write.turnero_llamar', [
                'request_id' => $requestId,
                'id' => $registro['id'] ?? $id,
                'turno' => $registro['turno'] ?? $turno,
                'estado' => $registro['estado'] ?? $estado,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.turnero_llamar.error', [
                'request_id' => $requestId,
                'id' => $id,
                'turno' => $turno,
                'estado' => $estado,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo llamar el turno solicitado'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $registro])->header('X-Request-Id', $requestId);
    }

    public function guardarDetallesCirugia(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);

        try {
            $result = $this->service->guardarDetallesCirugia($id, $payload);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            $errorRef = bin2hex(random_bytes(6));
            Log::error('solicitudes.write.guardar_cirugia.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error_ref' => $errorRef,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error interno (ref: ' . $errorRef . ')',
            ], 500)->header('X-Request-Id', $requestId);
        }

        return response()->json($result)->header('X-Request-Id', $requestId);
    }

    public function guardarDerivacionPreseleccion(Request $request): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);

        try {
            $saved = $this->service->guardarDerivacionPreseleccion(
                isset($payload['solicitud_id']) ? (int) $payload['solicitud_id'] : null,
                $payload,
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422)
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.guardar_derivacion_preseleccion.error', [
                'request_id' => $requestId,
                'payload_keys' => array_keys($payload),
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'No se pudo guardar la derivación seleccionada.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => $saved])->header('X-Request-Id', $requestId);
    }

    public function crmGuardarDetalles(Request $request, int $id): JsonResponse
    {
        return $this->crmWriteResponse(
            $request,
            $id,
            fn(array $payload, ?int $userId): array => $this->service->crmGuardarDetalles($id, $payload, $userId)
        );
    }

    public function crmBootstrap(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->crmBootstrap($id, $this->payload($request), LegacySessionAuth::userId($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_bootstrap.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true] + $result)->header('X-Request-Id', $requestId);
    }

    public function crmChecklistState(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->crmChecklistState($id);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_checklist_state.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo cargar el checklist'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true] + $result)->header('X-Request-Id', $requestId);
    }

    public function crmActualizarChecklist(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $etapa = trim((string) ($payload['etapa_slug'] ?? ($payload['etapa'] ?? '')));
        $completado = isset($payload['completado']) ? (bool) $payload['completado'] : true;

        if ($etapa === '') {
            return response()->json(['success' => false, 'error' => 'Etapa requerida'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $result = $this->service->crmActualizarChecklist($id, $etapa, $completado, LegacySessionAuth::userId($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_checklist.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'etapa' => $etapa,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo sincronizar el checklist con CRM'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true] + $result)->header('X-Request-Id', $requestId);
    }

    public function crmAgregarNota(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $nota = trim((string) ($this->payload($request)['nota'] ?? ''));
        if ($nota === '') {
            return response()->json(['success' => false, 'error' => 'La nota no puede estar vacía'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $summary = $this->service->crmAgregarNota($id, $nota, LegacySessionAuth::userId($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_nota.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo registrar la nota'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $summary])->header('X-Request-Id', $requestId);
    }

    public function crmGuardarTarea(Request $request, int $id): JsonResponse
    {
        return $this->crmWriteResponse(
            $request,
            $id,
            fn(array $payload, ?int $userId): array => $this->service->crmGuardarTarea($id, $payload, $userId)
        );
    }

    public function crmRegistrarBloqueo(Request $request, int $id): JsonResponse
    {
        return $this->crmWriteResponse(
            $request,
            $id,
            fn(array $payload, ?int $userId): array => $this->service->crmRegistrarBloqueo($id, $payload, $userId)
        );
    }

    public function crmSubirAdjunto(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $file = $request->file('archivo');
        if (!$file instanceof UploadedFile) {
            return response()->json(['success' => false, 'error' => 'No se recibió el archivo'], 422)
                ->header('X-Request-Id', $requestId);
        }

        if (!$file->isValid()) {
            return response()->json(['success' => false, 'error' => 'El archivo es inválido'], 422)
                ->header('X-Request-Id', $requestId);
        }

        $originalName = trim((string) $file->getClientOriginalName());
        if ($originalName === '') {
            $originalName = 'adjunto';
        }

        $safeName = preg_replace('/[^A-Za-z0-9_\.-]+/', '_', $originalName) ?? 'adjunto';
        $safeName = trim($safeName, '_');
        if ($safeName === '') {
            $safeName = 'adjunto';
        }

        $publicRoot = $this->resolveSharedPublicPath();
        $uploadDir = rtrim($publicRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'solicitudes' . DIRECTORY_SEPARATOR . $id;
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return response()->json(['success' => false, 'error' => 'No se pudo preparar la carpeta de adjuntos'], 500)
                ->header('X-Request-Id', $requestId);
        }

        $storedName = uniqid('crm_', true) . '_' . $safeName;
        $storedPath = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
        $relativePath = 'uploads/solicitudes/' . $id . '/' . $storedName;
        $description = trim((string) $request->input('descripcion', ''));
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType();
        $size = $file->getSize();

        try {
            $file->move($uploadDir, $storedName);
            $summary = $this->service->crmSubirAdjunto(
                $id,
                $originalName,
                $relativePath,
                is_string($mimeType) && $mimeType !== '' ? $mimeType : null,
                is_numeric($size) ? (int) $size : null,
                LegacySessionAuth::userId($request),
                $description !== '' ? $description : null,
            );
        } catch (RuntimeException $e) {
            if (is_file($storedPath)) {
                @unlink($storedPath);
            }

            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            if (is_file($storedPath)) {
                @unlink($storedPath);
            }

            Log::error('solicitudes.write.crm_adjunto.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo registrar el adjunto'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $summary])->header('X-Request-Id', $requestId);
    }

    public function crmActualizarTarea(Request $request, int $id): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        $payload = $this->payload($request);
        $taskId = isset($payload['tarea_id']) ? (int) $payload['tarea_id'] : 0;
        $estado = trim((string) ($payload['estado'] ?? ''));

        if ($taskId <= 0 || $estado === '') {
            return response()->json(['success' => false, 'error' => 'Datos incompletos'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $summary = $this->service->crmActualizarTareaEstado($id, $taskId, $estado);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_tarea_estado.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'task_id' => $taskId,
                'estado' => $estado,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudo actualizar la tarea'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $summary])->header('X-Request-Id', $requestId);
    }

    /**
     * @param callable(array<string,mixed>, ?int): array<string,mixed> $callback
     */
    private function crmWriteResponse(Request $request, int $id, callable $callback): JsonResponse
    {
        $requestId = $this->requestId($request);
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'error' => 'Sesión expirada'], 401)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $summary = $callback($this->payload($request), LegacySessionAuth::userId($request));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], $this->runtimeStatus($e))
                ->header('X-Request-Id', $requestId);
        } catch (Throwable $e) {
            Log::error('solicitudes.write.crm_write.error', [
                'request_id' => $requestId,
                'solicitud_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'error' => 'No se pudieron guardar los cambios del CRM'], 500)
                ->header('X-Request-Id', $requestId);
        }

        return response()->json(['success' => true, 'data' => $summary])->header('X-Request-Id', $requestId);
    }

    private function runtimeStatus(RuntimeException $e): int
    {
        $message = trim($e->getMessage());
        if (strcasecmp($message, 'Solicitud no encontrada') === 0) {
            return 404;
        }

        $code = (int) $e->getCode();
        if ($code >= 400 && $code < 500) {
            return $code;
        }

        return 422;
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(Request $request): array
    {
        $all = $request->all();
        $json = $request->json()->all();

        if (!is_array($all)) {
            $all = [];
        }
        if (!is_array($json)) {
            $json = [];
        }

        return array_merge($all, $json);
    }

    private function requestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        return 'v2-' . bin2hex(random_bytes(8));
    }

    private function resolveSharedPublicPath(): string
    {
        $configured = trim((string) (env('SHARED_PUBLIC_PATH') ?? ''));
        if ($configured !== '') {
            return rtrim($configured, DIRECTORY_SEPARATOR);
        }

        $legacyCandidate = realpath(base_path('..' . DIRECTORY_SEPARATOR . 'public'));
        if (is_string($legacyCandidate) && $legacyCandidate !== '') {
            return $legacyCandidate;
        }

        return public_path();
    }
}
