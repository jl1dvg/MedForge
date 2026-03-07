<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\CirugiaService;
use App\Modules\Cirugias\Services\CirugiasDerivacionService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;

class CirugiasWriteController
{
    private CirugiaService $service;
    private CirugiasDerivacionService $derivacionService;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->service = new CirugiaService($pdo);
        $this->derivacionService = new CirugiasDerivacionService($pdo, dirname(base_path()));
    }

    public function guardar(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesion expirada'], 401);
        }

        $payload = $request->all();
        $exito = $this->service->guardar($payload);

        $statusCode = $exito ? 200 : 500;
        $response = [
            'success' => $exito,
            'message' => $exito
                ? 'Operación completada.'
                : ($this->service->getLastError() ?? 'No se pudo guardar la información del protocolo.'),
        ];

        if ($exito && !empty($payload['form_id'])) {
            $protocoloId = $this->service->obtenerProtocoloIdPorFormulario(
                (string) $payload['form_id'],
                isset($payload['hc_number']) ? (string) $payload['hc_number'] : null
            );
            if ($protocoloId !== null) {
                $response['protocolo_id'] = $protocoloId;
            }

            if (!empty($payload['status']) && (int) $payload['status'] === 1) {
                $this->service->actualizarStatus(
                    (string) $payload['form_id'],
                    (string) ($payload['hc_number'] ?? ''),
                    1,
                    LegacySessionAuth::userId($request)
                );
            }
        }

        return response()->json($response, $statusCode);
    }

    public function autosave(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesion expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
        }

        $insumos = $request->input('insumos');
        $medicamentos = $request->input('medicamentos');

        $success = $this->service->guardarAutosave(
            $formId,
            $hcNumber,
            is_string($insumos) ? $insumos : null,
            is_string($medicamentos) ? $medicamentos : null,
        );

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => $this->service->getLastError() ?? 'No se pudo guardar el autosave.',
            ], 500);
        }

        return response()->json(['success' => true]);
    }

    public function scrapeDerivacion(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesion expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()->json([
                'success' => false,
                'message' => 'Faltan parámetros obligatorios.',
            ], 400);
        }

        try {
            $result = $this->derivacionService->scrapearDerivacion($formId, $hcNumber);
        } catch (\Throwable $exception) {
            Log::error('cirugias.scrape_derivacion.error', [
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo ejecutar el scraper de derivación.',
            ], 500);
        }

        if (($result['payload'] ?? null) === null && (int) ($result['exit_code'] ?? 0) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'El scraper no devolvió datos válidos.',
                'exit_code' => (int) ($result['exit_code'] ?? 0),
                'raw_output' => (string) ($result['raw_output'] ?? ''),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Datos de derivación procesados.',
            'data' => [
                'diagnosticos_previos' => $result['diagnosticos_previos'] ?? [],
                'scraper' => $result['payload'],
                'derivacion_sync' => (bool) ($result['derivacion_sync'] ?? false),
                'exit_code' => (int) ($result['exit_code'] ?? 0),
            ],
            'raw_output' => (string) ($result['raw_output'] ?? ''),
            'warning' => $this->derivacionService->getLastError(),
        ]);
    }

    public function togglePrinted(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesion expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));
        $printedRaw = $request->input('printed');

        if ($formId === '' || $hcNumber === '' || $printedRaw === null) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
        }

        $printed = (int) $printedRaw;
        $ok = $this->service->actualizarPrinted($formId, $hcNumber, $printed);

        return response()->json(['success' => $ok]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['success' => false, 'message' => 'Sesion expirada'], 401);
        }

        $formId = trim((string) $request->input('form_id', ''));
        $hcNumber = trim((string) $request->input('hc_number', ''));
        $statusRaw = $request->input('status');

        if ($formId === '' || $hcNumber === '' || $statusRaw === null) {
            return response()->json(['success' => false, 'message' => 'Faltan parámetros'], 400);
        }

        $status = (int) $statusRaw;
        $ok = $this->service->actualizarStatus($formId, $hcNumber, $status, LegacySessionAuth::userId($request));

        return response()->json(['success' => $ok]);
    }
}
