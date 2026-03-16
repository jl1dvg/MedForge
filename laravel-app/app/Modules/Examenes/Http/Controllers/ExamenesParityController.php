<?php

declare(strict_types=1);

namespace App\Modules\Examenes\Http\Controllers;

use App\Modules\Examenes\Services\ExamenesParityService;
use App\Modules\Examenes\Services\ExamenesPrefacturaService;
use App\Modules\Examenes\Services\ExamenesReportingService;
use App\Modules\Examenes\Services\ImagenesUiService;
use App\Modules\Examenes\Services\LegacyExamenesBridge;
use App\Modules\Examenes\Services\LegacyExamenesRuntime;
use App\Modules\Examenes\Services\NasImagenesService;
use App\Modules\Shared\Support\LegacySessionAuth;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Modules\Examenes\Models\ExamenModel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ExamenesParityController
{
    private LegacyExamenesBridge $bridge;
    private ExamenesParityService $native;
    private ExamenesPrefacturaService $prefactura;
    private ExamenesReportingService $reporting;
    private ImagenesUiService $imagenesUi;
    private NasImagenesService $nasImagenesService;
    private ?ExamenModel $legacyExamenModel = null;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->bridge = new LegacyExamenesBridge();
        $this->native = new ExamenesParityService($pdo);
        $this->prefactura = new ExamenesPrefacturaService($pdo);
        $this->reporting = new ExamenesReportingService($pdo);
        $this->imagenesUi = new ImagenesUiService($pdo);
        $this->nasImagenesService = new NasImagenesService();
    }

    public function kanbanData(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'kanbanData',
            fn(): array => $this->native->kanbanData($request->all())
        );
    }

    public function enviarCoberturaMail(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'enviarCoberturaMail',
            fn(): array => $this->prefactura->sendCoberturaMail(
                $this->payload($request),
                $request->file('attachment'),
                LegacySessionAuth::userId($request)
            )
        );
    }

    public function reportePdf(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        try {
            $result = $this->reporting->generatePdf($this->payload($request), $this->sessionPermissions($request));

            return response((string) ($result['content'] ?? ''), (int) ($result['status'] ?? 200), $result['headers'] ?? []);
        } catch (Throwable $e) {
            Log::warning('examenes.v2.native.fallback', [
                'method' => 'reportePdf',
                'error' => $e->getMessage(),
            ]);

            return $this->dispatch($request, 'reportePdf', [], false);
        }
    }

    public function reporteExcel(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        try {
            $result = $this->reporting->generateExcel($this->payload($request), $this->sessionPermissions($request));

            return response((string) ($result['content'] ?? ''), (int) ($result['status'] ?? 200), $result['headers'] ?? []);
        } catch (Throwable $e) {
            Log::warning('examenes.v2.native.fallback', [
                'method' => 'reporteExcel',
                'error' => $e->getMessage(),
            ]);

            return $this->dispatch($request, 'reporteExcel', [], false);
        }
    }

    public function actualizarEstado(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'actualizarEstado',
            fn(): array => $this->native->actualizarEstado($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function enviarRecordatorios(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'enviarRecordatorios',
            fn(): array => $this->reporting->dispatchReminders($this->payload($request))
        );
    }

    public function derivacionPreseleccion(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'derivacionPreseleccion',
            fn(): array => $this->native->derivacionPreseleccion($request->all())
        );
    }

    public function guardarDerivacionPreseleccion(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'guardarDerivacionPreseleccion',
            fn(): array => $this->native->guardarDerivacionPreseleccion($request->all())
        );
    }

    public function apiEstadoGet(Request $request): Response
    {
        $hcNumber = $request->query('hcNumber', $request->query('hc_number'));

        return $this->relayNativeJson(
            $request,
            'apiEstadoGet',
            fn(): array => $this->native->apiEstadoGet(is_scalar($hcNumber) ? (string) $hcNumber : null)
        );
    }

    public function apiEstadoPost(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'apiEstadoPost',
            fn(): array => $this->native->apiEstadoPost($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function turneroData(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'turneroData',
            fn(): array => $this->native->turneroData($request->query())
        );
    }

    public function derivacionDetalle(Request $request): Response
    {
        $hcNumber = $request->query('hc_number');
        $formId = $request->query('form_id');
        $examenId = $request->query('examen_id');

        return $this->relayNativeJson(
            $request,
            'derivacionDetalle',
            fn(): array => $this->native->derivacionDetalle(
                is_scalar($hcNumber) ? (string) $hcNumber : null,
                is_scalar($formId) ? (string) $formId : null,
                is_scalar($examenId) ? (int) $examenId : null,
                LegacySessionAuth::userId($request)
            )
        );
    }

    public function turneroLlamar(Request $request): Response
    {
        return $this->relayNativeJson(
            $request,
            'turneroLlamar',
            fn(): array => $this->native->turneroLlamar($request->all(), LegacySessionAuth::userId($request))
        );
    }

    public function crmResumen(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmResumen',
            fn(): array => $this->native->crmResumen($id),
            [$id]
        );
    }

    public function crmGuardarDetalles(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmGuardarDetalles',
            fn(): array => $this->native->crmGuardarDetalles($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmBootstrap(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmBootstrap',
            fn(): array => $this->native->crmBootstrap(
                $id,
                $request->all(),
                LegacySessionAuth::userId($request),
                $this->sessionPermissions($request)
            ),
            [$id]
        );
    }

    public function crmChecklistState(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmChecklistState',
            fn(): array => $this->native->crmChecklistState($id, $this->sessionPermissions($request)),
            [$id]
        );
    }

    public function crmActualizarChecklist(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmActualizarChecklist',
            fn(): array => $this->native->crmActualizarChecklist(
                $id,
                $request->all(),
                LegacySessionAuth::userId($request),
                $this->sessionPermissions($request)
            ),
            [$id]
        );
    }

    public function crmAgregarNota(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmAgregarNota',
            fn(): array => $this->native->crmAgregarNota($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmRegistrarBloqueo(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmRegistrarBloqueo',
            fn(): array => $this->native->crmRegistrarBloqueo($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmGuardarTarea(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmGuardarTarea',
            fn(): array => $this->native->crmGuardarTarea($id, $request->all(), LegacySessionAuth::userId($request)),
            [$id]
        );
    }

    public function crmActualizarTarea(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmActualizarTarea',
            fn(): array => $this->native->crmActualizarTarea($id, $request->all()),
            [$id]
        );
    }

    public function crmSubirAdjunto(Request $request, int $id): Response
    {
        return $this->relayNativeJson(
            $request,
            'crmSubirAdjunto',
            fn(): array => $this->native->crmSubirAdjunto(
                $id,
                $request->file('archivo'),
                is_scalar($request->input('descripcion')) ? (string) $request->input('descripcion') : null,
                LegacySessionAuth::userId($request)
            ),
            [$id]
        );
    }

    public function prefactura(Request $request): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $hcNumber = $request->query('hc_number');
        $formId = $request->query('form_id');
        $examenId = $request->query('examen_id');

        $hcNumber = is_scalar($hcNumber) ? trim((string) $hcNumber) : '';
        $formId = is_scalar($formId) ? trim((string) $formId) : '';
        $resolvedExamenId = is_scalar($examenId) ? (int) $examenId : null;

        if ($hcNumber === '' || $formId === '') {
            return response('<p class="text-danger mb-0">Faltan parámetros para mostrar el detalle del examen.</p>', 400);
        }

        try {
            $viewData = $this->prefactura->buildPrefacturaViewData(
                $hcNumber,
                $formId,
                $resolvedExamenId,
                LegacySessionAuth::userId($request)
            );

            if (empty($viewData['examen'])) {
                return response('<p class="text-danger mb-0">No se encontraron datos para el examen seleccionado.</p>', 404);
            }

            return response()->view('examenes.prefactura_detalle', [
                'viewData' => $viewData,
            ]);
        } catch (Throwable $e) {
            Log::warning('examenes.v2.native.fallback', [
                'method' => 'prefactura',
                'error' => $e->getMessage(),
            ]);

            return $this->dispatch($request, 'prefactura', [], false);
        }
    }

    public function imagenesNasList(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        $hcNumber = trim((string) $request->query('hc_number', ''));
        $formId = trim((string) $request->query('form_id', ''));

        if ($hcNumber === '' || $formId === '') {
            return response()->json([
                'success' => false,
                'error' => 'Faltan parámetros para consultar imágenes.',
            ], 422);
        }

        if (!$this->nasImagenesService->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => $this->nasImagenesService->getLastError() ?? 'NAS no disponible.',
            ], 500);
        }

        $nasContext = $this->resolveNasContext($hcNumber, $formId);
        $resolvedHcNumber = $nasContext['hc_number'];
        $resolvedFormId = $nasContext['form_id'];

        if (!$nasContext['has_image_context']) {
            return response()->json([
                'success' => true,
                'files' => [],
                'error' => null,
                'message' => 'No existe un procedimiento de imagenes asociado a este examen.',
                'resolved_form_id' => $resolvedFormId,
                'resolved_hc_number' => $resolvedHcNumber,
            ]);
        }

        $error = null;
        $files = $this->getNasFilesWithCache($resolvedHcNumber, $resolvedFormId, false, $error);
        $files = array_map(function (array $file) use ($resolvedHcNumber, $resolvedFormId): array {
            $name = trim((string) ($file['name'] ?? ''));
            $file['url'] = $name === ''
                ? ''
                : '/v2/imagenes/examenes-realizados/nas/file?hc_number=' . rawurlencode($resolvedHcNumber)
                    . '&form_id=' . rawurlencode($resolvedFormId)
                    . '&file=' . rawurlencode($name);

            return $file;
        }, $files);

        return response()->json([
            'success' => $error === null,
            'files' => $files,
            'error' => $error,
            'resolved_form_id' => $resolvedFormId,
            'resolved_hc_number' => $resolvedHcNumber,
        ]);
    }

    public function imagenesNasWarm(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        if (!$this->nasImagenesService->isAvailable()) {
            return response()->json([
                'success' => false,
                'error' => $this->nasImagenesService->getLastError() ?? 'NAS no disponible.',
            ], 500);
        }

        $payload = $this->payload($request);
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if ($items === []) {
            return response()->json([
                'success' => true,
                'checked' => 0,
                'warmed' => 0,
            ]);
        }

        $checked = 0;
        $warmed = 0;
        foreach (array_slice($items, 0, 8) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $formId = trim((string) ($item['form_id'] ?? ''));
            $hcNumber = trim((string) ($item['hc_number'] ?? ''));
            if ($formId === '' || $hcNumber === '') {
                continue;
            }

            $nasContext = $this->resolveNasContext($hcNumber, $formId);
            $resolvedHcNumber = $nasContext['hc_number'];
            $resolvedFormId = $nasContext['form_id'];

            if (!$nasContext['has_image_context']) {
                continue;
            }

            $checked++;
            $error = null;
            $files = $this->getNasFilesWithCache($resolvedHcNumber, $resolvedFormId, false, $error);
            if ($error !== null || $files === []) {
                continue;
            }

            $first = $files[0] ?? null;
            $name = is_array($first) ? trim((string) ($first['name'] ?? '')) : '';
            if ($name === '') {
                continue;
            }

            if ($this->warmNasFileCache($resolvedHcNumber, $resolvedFormId, $name)) {
                $warmed++;
            }
        }

        return response()->json([
            'success' => true,
            'checked' => $checked,
            'warmed' => $warmed,
        ]);
    }

    public function imagenesNasFile(Request $request): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        $hcNumber = trim((string) $request->query('hc_number', ''));
        $formId = trim((string) $request->query('form_id', ''));
        $filename = trim((string) $request->query('file', ''));

        if ($hcNumber === '' || $formId === '' || $filename === '') {
            return response('Parámetros incompletos', 422);
        }

        if (!$this->nasImagenesService->isAvailable()) {
            return response($this->nasImagenesService->getLastError() ?? 'NAS no disponible.', 500);
        }

        $nasContext = $this->resolveNasContext($hcNumber, $formId);
        $resolvedHcNumber = $nasContext['hc_number'];
        $resolvedFormId = $nasContext['form_id'];

        if (!$nasContext['has_image_context']) {
            return response('No existe un procedimiento de imagenes asociado a este examen.', 404);
        }

        $cachePath = $this->resolveNasFileCachePath($resolvedHcNumber, $resolvedFormId, $filename);
        if ($cachePath !== null && is_file($cachePath) && $this->isNasCacheFresh($cachePath)) {
            return response()->file($cachePath, $this->nasResponseHeaders(
                $this->resolveNasMimeByFilename($filename),
                (int) (filesize($cachePath) ?: 0),
                basename($filename)
            ));
        }

        $opened = $this->nasImagenesService->openFile($resolvedHcNumber, $resolvedFormId, $filename);
        if (!$opened || empty($opened['stream'])) {
            return response($this->nasImagenesService->getLastError() ?? 'Archivo no encontrado.', 404);
        }

        $type = (string) ($opened['type'] ?? 'application/octet-stream');
        $size = (int) ($opened['size'] ?? 0);
        $name = (string) ($opened['name'] ?? $filename);
        /** @var resource $stream */
        $stream = $opened['stream'];
        $cacheTemp = $cachePath !== null ? $cachePath . '.part' : null;

        return response()->stream(function () use ($stream, $cachePath, $cacheTemp): void {
            $cacheHandle = null;
            if ($cacheTemp !== null) {
                $cacheHandle = @fopen($cacheTemp, 'wb');
            }

            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    break;
                }
                if ($cacheHandle) {
                    fwrite($cacheHandle, $chunk);
                }
                echo $chunk;
            }

            if ($cacheHandle) {
                fclose($cacheHandle);
                if ($cacheTemp !== null && $cachePath !== null) {
                    @rename($cacheTemp, $cachePath);
                }
            }

            fclose($stream);
        }, 200, $this->nasResponseHeaders($type, $size, $name));
    }

    /**
     * @return array{form_id:string,hc_number:string,has_image_context:bool}
     */
    private function resolveNasContext(string $hcNumber, string $formId): array
    {
        $resolvedFormId = trim($formId);
        $resolvedHcNumber = trim($hcNumber);
        if ($resolvedFormId === '' || $resolvedHcNumber === '') {
            return [
                'form_id' => $resolvedFormId,
                'hc_number' => $resolvedHcNumber,
                'has_image_context' => false,
            ];
        }

        try {
            $model = $this->legacyExamenModel();
            $procedimiento = $model->obtenerProcedimientoProyectadoPorFormHc($resolvedFormId, $resolvedHcNumber)
                ?? $model->obtenerProcedimientoProyectadoPorFormId($resolvedFormId);

            if (is_array($procedimiento) && $this->isImagenProcedimiento((string) ($procedimiento['procedimiento_proyectado'] ?? ''))) {
                $procedimientoHc = trim((string) ($procedimiento['hc_number'] ?? ''));

                return [
                    'form_id' => $resolvedFormId,
                    'hc_number' => $procedimientoHc !== '' ? $procedimientoHc : $resolvedHcNumber,
                    'has_image_context' => true,
                ];
            }

            $examen = $model->obtenerExamenPorFormHc($resolvedFormId, $resolvedHcNumber);
            if (!is_array($examen) || $examen === []) {
                return [
                    'form_id' => $resolvedFormId,
                    'hc_number' => $resolvedHcNumber,
                    'has_image_context' => false,
                ];
            }

            $candidate = $model->buscarProcedimientoImagenOrigen(
                trim((string) ($examen['hc_number'] ?? $resolvedHcNumber)) ?: $resolvedHcNumber,
                isset($examen['examen_codigo']) ? (string) $examen['examen_codigo'] : null,
                isset($examen['consulta_fecha']) ? (string) $examen['consulta_fecha'] : null,
                isset($examen['examen_nombre']) ? (string) $examen['examen_nombre'] : null
            );

            if (!is_array($candidate) || $candidate === []) {
                return [
                    'form_id' => $resolvedFormId,
                    'hc_number' => $resolvedHcNumber,
                    'has_image_context' => false,
                ];
            }

            $candidateFormId = trim((string) ($candidate['form_id'] ?? ''));
            $candidateHcNumber = trim((string) ($candidate['hc_number'] ?? ''));
            if ($candidateFormId === '' || $candidateHcNumber === '') {
                return [
                    'form_id' => $resolvedFormId,
                    'hc_number' => $resolvedHcNumber,
                    'has_image_context' => false,
                ];
            }

            return [
                'form_id' => $candidateFormId,
                'hc_number' => $candidateHcNumber,
                'has_image_context' => true,
            ];
        } catch (Throwable $e) {
            Log::warning('imagenes.v2.nas_context_resolver', [
                'form_id' => $resolvedFormId,
                'hc_number' => $resolvedHcNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'form_id' => $resolvedFormId,
                'hc_number' => $resolvedHcNumber,
                'has_image_context' => false,
            ];
        }
    }

    private function isImagenProcedimiento(string $procedimiento): bool
    {
        $procedimiento = trim($procedimiento);
        if ($procedimiento === '') {
            return false;
        }

        $normalized = function_exists('mb_strtoupper')
            ? mb_strtoupper($procedimiento, 'UTF-8')
            : strtoupper($procedimiento);

        return str_starts_with($normalized, 'IMAGENES');
    }

    private function legacyExamenModel(): ExamenModel
    {
        if ($this->legacyExamenModel instanceof ExamenModel) {
            return $this->legacyExamenModel;
        }

        LegacyExamenesRuntime::boot();
        $this->legacyExamenModel = new ExamenModel(DB::connection()->getPdo());

        return $this->legacyExamenModel;
    }

    public function imagenesDashboardExportPdf(Request $request): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        try {
            $payload = $this->imagenesUi->imagenesDashboardExportPayload($request->query());
            $report = is_array($payload['report'] ?? null) ? $payload['report'] : [];
            $filename = 'dashboard_imagenes_' . date('Ymd_His') . '.pdf';

            if (!class_exists(\Mpdf\Mpdf::class)) {
                throw new \RuntimeException('La librería mPDF no está disponible en el entorno.');
            }

            $html = view('examenes.pdf.imagenes-dashboard-kpi', [
                'generatedAt' => (new DateTimeImmutable('now'))->format('d/m/Y H:i'),
                'filterSummary' => is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [],
                'scopeNotice' => trim((string) ($report['scopeNotice'] ?? '')),
                'hallazgosClave' => is_array($report['hallazgosClave'] ?? null) ? $report['hallazgosClave'] : [],
                'methodology' => is_array($report['methodology'] ?? null) ? $report['methodology'] : [],
                'generalKpis' => is_array($report['generalKpis'] ?? null) ? $report['generalKpis'] : [],
                'temporalKpis' => is_array($report['temporalKpis'] ?? null) ? $report['temporalKpis'] : [],
                'economicKpis' => is_array($report['economicKpis'] ?? null) ? $report['economicKpis'] : [],
                'tables' => is_array($report['tables'] ?? null) ? $report['tables'] : [],
                'totalAtenciones' => (int) ($report['totalAtenciones'] ?? ($payload['total'] ?? 0)),
                'rangeLabel' => trim((string) ($report['rangeLabel'] ?? '')),
            ])->render();

            $pdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'orientation' => 'P',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 10,
                'margin_bottom' => 10,
            ]);
            $pdf->SetTitle('KPI Dashboard Imágenes');
            $pdf->WriteHTML($html);
            $pdf = (string) $pdf->Output('', 'S');

            if (strncmp($pdf, '%PDF-', 5) !== 0) {
                return response()->json(['error' => 'No se pudo generar el PDF (contenido inválido).'], 500);
            }

            return response($pdf, 200, [
                'Content-Length' => (string) strlen($pdf),
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $e) {
            Log::error('imagenes.v2.export.pdf', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'No se pudo generar el PDF.'], 500);
        }
    }

    public function imagenesDashboardExportExcel(Request $request): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        try {
            $payload = $this->imagenesUi->imagenesDashboardExportPayload($request->query());
            $detailRows = is_array($payload['detailRows'] ?? null) ? $payload['detailRows'] : [];
            $filtersSummary = is_array($payload['filtersSummary'] ?? null) ? $payload['filtersSummary'] : [];
            $report = is_array($payload['report'] ?? null) ? $payload['report'] : [];
            $filename = 'dashboard_imagenes_' . date('Ymd_His') . '.xlsx';

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Resumen KPI');
            $generatedAt = (new DateTimeImmutable('now'))->format('d/m/Y H:i');
            $row = 1;

            $this->writeExcelMergedTitle($sheet, $row, 'Dashboard de KPIs de imágenes', 'G');
            $row++;
            $sheet->setCellValue("A{$row}", 'Generado:');
            $sheet->setCellValue("B{$row}", $generatedAt);
            $sheet->setCellValue("D{$row}", 'Periodo:');
            $sheet->setCellValue("E{$row}", (string) ($report['rangeLabel'] ?? ''));
            $sheet->setCellValue("F{$row}", 'Registros:');
            $sheet->setCellValueExplicit("G{$row}", (string) count($detailRows), DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);

            $scopeNotice = trim((string) ($report['scopeNotice'] ?? ''));
            if ($scopeNotice !== '') {
                $row += 2;
                $sheet->setCellValue("A{$row}", $scopeNotice);
                $sheet->mergeCells("A{$row}:G{$row}");
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($this->excelNoticeStyle('EFF6FF', '1D4ED8'));
                $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true);
            }

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Filtros aplicados', 'G');
            $filterRows = [];
            foreach ($filtersSummary as $filter) {
                $filterRows[] = [
                    (string) ($filter['label'] ?? ''),
                    (string) ($filter['value'] ?? ''),
                ];
            }
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Filtro', 'Valor'],
                $filterRows,
                'Sin filtros específicos.',
                [26, 62]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Hallazgos clave', 'G');
            $hallazgosRows = array_map(
                static fn(string $item): array => [$item],
                array_values(array_filter(
                    is_array($report['hallazgosClave'] ?? null) ? $report['hallazgosClave'] : [],
                    static fn($item): bool => trim((string) $item) !== ''
                ))
            );
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Hallazgo'],
                $hallazgosRows,
                'No hubo suficientes datos para generar hallazgos destacados.',
                [96]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'Metodología', 'G');
            $methodologyRows = array_map(
                static fn(string $item): array => [$item],
                array_values(array_filter(
                    is_array($report['methodology'] ?? null) ? $report['methodology'] : [],
                    static fn($item): bool => trim((string) $item) !== ''
                ))
            );
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['Criterio'],
                $methodologyRows,
                'Sin metodología documentada.',
                [96]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Generales', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($report['generalKpis'] ?? null) ? $report['generalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI generales para el rango seleccionado.',
                [28, 16, 54]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Temporales', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Detalle'],
                $this->normalizeExcelRows(is_array($report['temporalKpis'] ?? null) ? $report['temporalKpis'] : [], ['label', 'value', 'note']),
                'Sin KPI temporales para el rango seleccionado.',
                [28, 16, 54]
            );

            $row += 2;
            $row = $this->writeExcelSectionHeader($sheet, $row, 'KPI Económicos', 'G');
            $row = $this->writeExcelTable(
                $sheet,
                $row,
                ['KPI', 'Valor', 'Qué significa', 'Cómo se calcula'],
                $this->normalizeExcelRows(is_array($report['economicKpis'] ?? null) ? $report['economicKpis'] : [], ['label', 'value', 'meaning', 'formula']),
                'Sin KPI económicos para el rango seleccionado.',
                [24, 16, 34, 34]
            );

            $tables = is_array($report['tables'] ?? null) ? $report['tables'] : [];
            foreach ($tables as $table) {
                $title = trim((string) ($table['title'] ?? 'Tabla'));
                $subtitle = trim((string) ($table['subtitle'] ?? ''));
                $row += 2;
                $row = $this->writeExcelSectionHeader($sheet, $row, $title, 'G');
                if ($subtitle !== '') {
                    $sheet->setCellValue("A{$row}", $subtitle);
                    $sheet->mergeCells("A{$row}:G{$row}");
                    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                    $sheet->getStyle("A{$row}:G{$row}")->getAlignment()->setWrapText(true);
                    $row++;
                }
                $headers = array_values(array_map(static fn($value): string => (string) $value, is_array($table['columns'] ?? null) ? $table['columns'] : []));
                $tableRows = [];
                foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $tableRow) {
                    $tableRows[] = array_map(static fn($value): string => (string) $value, is_array($tableRow) ? $tableRow : []);
                }
                $row = $this->writeExcelTable(
                    $sheet,
                    $row,
                    $headers,
                    $tableRows,
                    trim((string) ($table['empty_message'] ?? 'Sin datos.'))
                );
            }

            $sheet->freezePane('A4');
            foreach (['A' => 28, 'B' => 18, 'C' => 28, 'D' => 20, 'E' => 24, 'F' => 18, 'G' => 18] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $detailSheet = $spreadsheet->createSheet();
            $detailSheet->setTitle('Detalle');
            $detailHeaders = [
                '#',
                'Fecha',
                'HC',
                'Paciente',
                'Afiliación',
                'Categoría cliente',
                'Sede',
                'Estado encuentro',
                'Estado realización',
                'Estado informe',
                'Facturación',
                'Estado facturación operativa',
                'Fuente billing',
                'Monto estimado',
                'Honorario real',
                'Billing ID',
                'Fecha facturación',
                'Proc. facturados',
                'Archivos NAS',
                'Sin tarifa nivel 3',
                'Form ID',
                'Código tarifario',
                'Detalle tarifario',
            ];

            $detailRow = 1;
            foreach ($detailHeaders as $idx => $label) {
                $column = $this->excelColumnByIndex($idx);
                $detailSheet->setCellValue("{$column}{$detailRow}", $label);
            }
            $lastDetailColumn = $this->excelColumnByIndex(count($detailHeaders) - 1);
            $detailSheet->getStyle("A1:{$lastDetailColumn}1")->applyFromArray($this->excelTableHeaderStyle());
            $detailSheet->setAutoFilter("A1:{$lastDetailColumn}1");

            foreach ($detailRows as $index => $item) {
                $detailRow++;
                $values = [
                    (string) ($index + 1),
                    (string) ($item['fecha_examen'] ?? '—'),
                    (string) ($item['hc_number'] ?? ''),
                    (string) ($item['paciente'] ?? ''),
                    (string) ($item['afiliacion'] ?? ''),
                    (string) ($item['afiliacion_categoria'] ?? ''),
                    (string) ($item['sede'] ?? ''),
                    (string) ($item['estado_agenda'] ?? ''),
                    (string) ($item['estado_realizacion'] ?? ''),
                    (string) ($item['estado_informe'] ?? ''),
                    !empty($item['facturado']) ? 'FACTURADO' : 'PENDIENTE',
                    (string) ($item['estado_facturacion'] ?? ''),
                    $this->formatBillingSourceLabel((string) ($item['billing_source'] ?? '')),
                    (float) ($item['monto_pendiente_estimado'] ?? 0) > 0 ? number_format((float) ($item['monto_pendiente_estimado'] ?? 0), 2, '.', '') : '',
                    number_format((float) ($item['produccion'] ?? 0), 2, '.', ''),
                    (string) ($item['billing_id'] ?? ''),
                    (string) ($item['fecha_facturacion'] ?? '—'),
                    (string) ($item['procedimientos_facturados'] ?? 0),
                    !empty($item['nas_has_files']) ? (string) ($item['nas_files_count'] ?? 0) : '0',
                    !empty($item['sin_tarifa_publica']) ? 'SI' : 'NO',
                    (string) ($item['form_id'] ?? ''),
                    (string) ($item['codigo'] ?? ''),
                    (string) ($item['examen'] ?? ''),
                ];

                foreach ($values as $idx => $value) {
                    $column = $this->excelColumnByIndex($idx);
                    $detailSheet->setCellValueExplicit("{$column}{$detailRow}", $value, DataType::TYPE_STRING);
                }
            }

            if ($detailRow > 1) {
                $detailSheet->getStyle("A1:{$lastDetailColumn}{$detailRow}")->applyFromArray($this->excelTableBodyStyle());
                $detailSheet->getStyle("W2:W{$detailRow}")->getAlignment()->setWrapText(true);
            }

            $detailSheet->freezePane('A2');
            foreach ([
                'A' => 6, 'B' => 18, 'C' => 14, 'D' => 34, 'E' => 24, 'F' => 18, 'G' => 14, 'H' => 18,
                'I' => 20, 'J' => 18, 'K' => 14, 'L' => 24, 'M' => 16, 'N' => 16, 'O' => 16, 'P' => 14,
                'Q' => 18, 'R' => 14, 'S' => 12, 'T' => 16, 'U' => 12, 'V' => 14, 'W' => 56,
            ] as $column => $width) {
                $detailSheet->getColumnDimension($column)->setWidth($width);
            }

            $writer = new Xlsx($spreadsheet);
            $stream = fopen('php://temp', 'r+');
            $writer->save($stream);
            rewind($stream);
            $content = stream_get_contents($stream) ?: '';
            fclose($stream);
            $spreadsheet->disconnectWorksheets();

            if ($content === '' || strncmp($content, 'PK', 2) !== 0) {
                return response()->json(['error' => 'No se pudo generar el Excel (contenido inválido).'], 500);
            }

            return response($content, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Content-Length' => (string) strlen($content),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (Throwable $e) {
            Log::error('imagenes.v2.export.excel', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'No se pudo generar el Excel.'], 500);
        }
    }

    public function actualizarImagenRealizada(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        $payload = $this->payload($request);
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $tipoExamen = trim((string) ($payload['tipo_examen'] ?? ''));

        if ($id <= 0 || $tipoExamen === '') {
            return response()->json([
                'success' => false,
                'error' => 'Datos incompletos para actualizar',
            ], 422);
        }

        try {
            return response()->json([
                'success' => $this->imagenesUi->actualizarProcedimientoProyectado($id, $tipoExamen),
            ]);
        } catch (Throwable $e) {
            Log::error('imagenes.v2.actualizar', ['error' => $e->getMessage(), 'id' => $id]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo actualizar el procedimiento',
            ], 500);
        }
    }

    public function eliminarImagenRealizada(Request $request): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        $payload = $this->payload($request);
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($id <= 0) {
            return response()->json([
                'success' => false,
                'error' => 'ID inválido',
            ], 422);
        }

        try {
            return response()->json([
                'success' => $this->imagenesUi->eliminarProcedimientoProyectado($id),
            ]);
        } catch (Throwable $e) {
            Log::error('imagenes.v2.eliminar', ['error' => $e->getMessage(), 'id' => $id]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo eliminar el procedimiento',
            ], 500);
        }
    }

    /**
     * @param array<string,string|null> $headers
     * @return array<string,string>
     */
    private function nasResponseHeaders(string $type, int $size, string $name): array
    {
        $headers = [
            'Content-Type' => $type,
            'Content-Disposition' => 'inline; filename="' . basename($name) . '"',
            'Cache-Control' => 'private, max-age=1800',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($size > 0) {
            $headers['Content-Length'] = (string) $size;
        }

        return $headers;
    }

    /**
     * @return array<int,array{name:string,size:int,mtime:int,ext:string,type:string}>
     */
    private function getNasFilesWithCache(string $hcNumber, string $formId, bool $forceRefresh = false, ?string &$error = null): array
    {
        $error = null;
        if (!$forceRefresh) {
            $cached = $this->readNasListCache($hcNumber, $formId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $files = $this->nasImagenesService->listFiles($hcNumber, $formId);
        $error = $this->nasImagenesService->getLastError();
        if ($error === null) {
            $this->writeNasListCache($hcNumber, $formId, $files);
        }

        return $files;
    }

    /**
     * @return array<int,array{name:string,size:int,mtime:int,ext:string,type:string}>|null
     */
    private function readNasListCache(string $hcNumber, string $formId): ?array
    {
        $path = $this->resolveNasListCachePath($hcNumber, $formId);
        if ($path === null || !$this->isNasListCacheFresh($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !is_array($decoded['files'] ?? null)) {
            return null;
        }

        $files = [];
        foreach ($decoded['files'] as $file) {
            if (!is_array($file)) {
                continue;
            }
            $name = trim((string) ($file['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ext = strtolower(trim((string) ($file['ext'] ?? pathinfo($name, PATHINFO_EXTENSION))));
            $files[] = [
                'name' => $name,
                'size' => (int) ($file['size'] ?? 0),
                'mtime' => (int) ($file['mtime'] ?? 0),
                'ext' => $ext,
                'type' => (string) ($file['type'] ?? $this->resolveNasMimeByFilename($name)),
            ];
        }

        return $files;
    }

    /**
     * @param array<int,array{name:string,size:int,mtime:int,ext:string,type:string}> $files
     */
    private function writeNasListCache(string $hcNumber, string $formId, array $files): void
    {
        $path = $this->resolveNasListCachePath($hcNumber, $formId);
        if ($path === null) {
            return;
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $payload = json_encode([
            'cached_at' => date('c'),
            'files' => array_values($files),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }

        $tmp = $path . '.part';
        if (@file_put_contents($tmp, $payload) === false) {
            @unlink($tmp);
            return;
        }

        @rename($tmp, $path);
    }

    private function resolveNasListCachePath(string $hcNumber, string $formId): ?string
    {
        $dir = $this->resolveNasCacheDir();
        if ($dir === null) {
            return null;
        }

        $subdir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'list';
        if (!is_dir($subdir) && !@mkdir($subdir, 0775, true) && !is_dir($subdir)) {
            return null;
        }

        $hash = sha1($hcNumber . '|' . $formId . '|list');
        return $subdir . DIRECTORY_SEPARATOR . $hash . '.json';
    }

    private function isNasListCacheFresh(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $ttl = (int) ($_ENV['NAS_IMAGES_LIST_CACHE_TTL'] ?? $_SERVER['NAS_IMAGES_LIST_CACHE_TTL'] ?? 90);
        if ($ttl <= 0) {
            return false;
        }

        $mtime = (int) (filemtime($path) ?: 0);
        return $mtime > 0 && (time() - $mtime) <= $ttl;
    }

    private function warmNasFileCache(string $hcNumber, string $formId, string $filename): bool
    {
        $cachePath = $this->resolveNasFileCachePath($hcNumber, $formId, $filename);
        if ($cachePath === null) {
            return false;
        }

        if ($this->isNasCacheFresh($cachePath)) {
            return true;
        }

        $opened = $this->nasImagenesService->openFile($hcNumber, $formId, $filename);
        if (!$opened || empty($opened['stream'])) {
            return false;
        }

        /** @var resource $stream */
        $stream = $opened['stream'];
        $tmpPath = $cachePath . '.part';
        $handle = @fopen($tmpPath, 'wb');
        if (!$handle) {
            fclose($stream);
            return false;
        }

        while (!feof($stream)) {
            $chunk = fread($stream, 65536);
            if ($chunk === false) {
                break;
            }
            fwrite($handle, $chunk);
        }
        fclose($stream);
        fclose($handle);

        if (!is_file($tmpPath) || (int) (filesize($tmpPath) ?: 0) <= 0) {
            @unlink($tmpPath);
            return false;
        }

        @rename($tmpPath, $cachePath);
        return is_file($cachePath) && (int) (filesize($cachePath) ?: 0) > 0;
    }

    private function resolveNasFileCachePath(string $hcNumber, string $formId, string $filename): ?string
    {
        $dir = $this->resolveNasCacheDir();
        if ($dir === null) {
            return null;
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }

        $ext = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            $ext = 'bin';
        }

        $hash = sha1($hcNumber . '|' . $formId . '|' . $filename);
        return rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $hash . '.' . $ext;
    }

    private function resolveNasCacheDir(): ?string
    {
        $fromEnv = trim((string) ($_ENV['NAS_IMAGES_CACHE_DIR'] ?? $_SERVER['NAS_IMAGES_CACHE_DIR'] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $tmp = sys_get_temp_dir();
        if (!is_dir($tmp) || !is_writable($tmp)) {
            return null;
        }

        return rtrim($tmp, '/\\') . DIRECTORY_SEPARATOR . 'medforge_nas_cache';
    }

    private function isNasCacheFresh(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $ttl = (int) ($_ENV['NAS_IMAGES_CACHE_TTL'] ?? $_SERVER['NAS_IMAGES_CACHE_TTL'] ?? 1800);
        if ($ttl <= 0) {
            return false;
        }

        $mtime = (int) (filemtime($path) ?: 0);
        return $mtime > 0 && (time() - $mtime) <= $ttl;
    }

    private function resolveNasMimeByFilename(string $filename): string
    {
        return match (strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };
    }

    private function writeExcelMergedTitle(Worksheet $sheet, int $row, string $title, string $lastColumn = 'G'): void
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F766E'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function excelNoticeStyle(string $fillColor, string $textColor): array
    {
        return [
            'font' => [
                'italic' => true,
                'color' => ['rgb' => $textColor],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $fillColor],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'BFDBFE'],
                ],
            ],
            'alignment' => [
                'wrapText' => true,
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ];
    }

    private function writeExcelSectionHeader(Worksheet $sheet, int $row, string $title, string $lastColumn = 'G'): int
    {
        $sheet->setCellValue("A{$row}", $title);
        $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => '0F172A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E2E8F0'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
        ]);

        return $row + 1;
    }

    /**
     * @param array<int,string> $headers
     * @param array<int,array<int,string>> $rows
     * @param array<int,int|float> $widths
     */
    private function writeExcelTable(
        Worksheet $sheet,
        int $row,
        array $headers,
        array $rows,
        string $emptyMessage,
        array $widths = []
    ): int {
        if ($headers === []) {
            return $row;
        }

        $lastColumn = $this->excelColumnByIndex(count($headers) - 1);
        foreach ($headers as $index => $header) {
            $column = $this->excelColumnByIndex($index);
            $sheet->setCellValue("{$column}{$row}", $header);
            if (isset($widths[$index])) {
                $sheet->getColumnDimension($column)->setWidth((float) $widths[$index]);
            }
        }
        $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray($this->excelTableHeaderStyle());
        $bodyStart = $row + 1;

        if ($rows === []) {
            $sheet->setCellValue("A{$bodyStart}", $emptyMessage);
            $sheet->mergeCells("A{$bodyStart}:{$lastColumn}{$bodyStart}");
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->applyFromArray($this->excelTableBodyStyle());
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$bodyStart}")->getAlignment()->setWrapText(true);

            return $bodyStart;
        }

        $currentRow = $bodyStart;
        foreach ($rows as $dataRow) {
            foreach ($headers as $index => $_header) {
                $column = $this->excelColumnByIndex($index);
                $sheet->setCellValueExplicit(
                    "{$column}{$currentRow}",
                    (string) ($dataRow[$index] ?? ''),
                    DataType::TYPE_STRING
                );
            }
            $currentRow++;
        }

        $endRow = $currentRow - 1;
        $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$endRow}")->applyFromArray($this->excelTableBodyStyle());
        $sheet->getStyle("A{$bodyStart}:{$lastColumn}{$endRow}")->getAlignment()->setWrapText(true);

        return $endRow;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<int,string> $keys
     * @return array<int,array<int,string>>
     */
    private function normalizeExcelRows(array $rows, array $keys): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $normalizedRow = [];
            foreach ($keys as $key) {
                $normalizedRow[] = trim((string) ($row[$key] ?? ''));
            }
            $normalized[] = $normalizedRow;
        }

        return $normalized;
    }

    /**
     * @return array<string,mixed>
     */
    private function excelTableHeaderStyle(): array
    {
        return [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '0F172A'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function excelTableBodyStyle(): array
    {
        return [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
            ],
        ];
    }

    private function formatBillingSourceLabel(string $source): string
    {
        return match (trim($source)) {
            'real' => 'Billing real',
            'public' => 'Billing público',
            default => '',
        };
    }

    private function excelColumnByIndex(int $index): string
    {
        $index = max(0, $index);
        $column = '';

        do {
            $remainder = $index % 26;
            $column = chr(65 + $remainder) . $column;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $column;
    }

    /**
     * @param array<int,mixed> $args
     */
    private function relayJson(Request $request, string $legacyMethod, array $args = []): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        return $this->dispatch($request, $legacyMethod, $args, true);
    }

    /**
     * @param array<int,mixed> $args
     */
    private function relayRaw(Request $request, string $legacyMethod, array $args = []): Response|RedirectResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return redirect('/auth/login?auth_required=1');
        }

        return $this->dispatch($request, $legacyMethod, $args, false);
    }

    /**
     * @param callable():array{status:int,payload:array<string,mixed>} $nativeResolver
     */
    private function relayNativeJson(
        Request $request,
        string $legacyMethod,
        callable $nativeResolver,
        array $legacyArgs = []
    ): Response
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'success' => false,
                'error' => 'Sesión expirada',
            ], 401);
        }

        try {
            $nativeResult = $nativeResolver();
            $status = (int) ($nativeResult['status'] ?? 200);
            if ($status < 100 || $status > 599) {
                $status = 200;
            }

            $payload = is_array($nativeResult['payload'] ?? null) ? $nativeResult['payload'] : [];

            return response()->json($payload, $status);
        } catch (Throwable $e) {
            Log::warning('examenes.v2.native.fallback', [
                'method' => $legacyMethod,
                'error' => $e->getMessage(),
            ]);

            return $this->dispatch($request, $legacyMethod, $legacyArgs, true);
        }
    }

    /**
     * @return array<int,string>
     */
    private function sessionPermissions(Request $request): array
    {
        $session = LegacySessionAuth::readSession($request);
        $raw = $session['permisos'] ?? [];

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $split = array_map('trim', explode(',', $raw));
                $raw = array_values(array_filter($split, static fn(string $item): bool => $item !== ''));
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $permissions = [];
        array_walk_recursive($raw, static function (mixed $value) use (&$permissions): void {
            if (!is_string($value)) {
                return;
            }

            $permission = trim($value);
            if ($permission === '') {
                return;
            }

            $permissions[] = $permission;
        });

        return array_values(array_unique($permissions));
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

    /**
     * @param array<int,mixed> $args
     */
    private function dispatch(Request $request, string $legacyMethod, array $args, bool $expectJson): Response
    {
        try {
            $captured = $this->bridge->dispatch($request, $legacyMethod, $args);
        } catch (Throwable $e) {
            Log::error('examenes.v2.parity.error', [
                'method' => $legacyMethod,
                'error' => $e->getMessage(),
            ]);

            if ($expectJson) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo procesar la solicitud en v2.',
                ], 500);
            }

            return response('No se pudo procesar la solicitud en v2.', 500);
        }

        $status = (int) ($captured['status'] ?? 200);
        if ($status < 100 || $status > 599) {
            $status = 200;
        }

        $headers = is_array($captured['headers'] ?? null) ? $captured['headers'] : [];
        if ($expectJson && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json; charset=UTF-8';
        }

        return response((string) ($captured['body'] ?? ''), $status, $headers);
    }
}
