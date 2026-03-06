<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Modules\Reporting\Services\ImagenesReportDataService;
use App\Modules\Reporting\Services\PostSurgeryRestReportDataService;
use App\Modules\Reporting\Services\ProtocolReportDataService;
use App\Modules\Reporting\Services\CoberturaReportDataService;
use App\Modules\Reporting\Services\ConsultaReportDataService;
use App\Modules\Reporting\Services\ReportPdfService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ReportingReadController
{
    private ProtocolReportDataService $protocolService;
    private ImagenesReportDataService $imagenesService;
    private CoberturaReportDataService $coberturaService;
    private ConsultaReportDataService $consultaService;
    private PostSurgeryRestReportDataService $postSurgeryRestService;
    private ?ReportPdfService $reportPdfService = null;

    public function __construct()
    {
        $this->protocolService = new ProtocolReportDataService();
        $this->imagenesService = new ImagenesReportDataService();
        $this->coberturaService = new CoberturaReportDataService();
        $this->consultaService = new ConsultaReportDataService();
        $this->postSurgeryRestService = new PostSurgeryRestReportDataService();
    }

    public function protocolData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->protocolService->buildProtocolData($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.protocol_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data del reporte.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró el protocolo solicitado.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.protocol_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-protocol-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function protocolPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));
        $mode = trim((string) $request->query('modo', 'completo'));
        $page = trim((string) $request->query('pagina', ''));
        if ($page === '') {
            $page = null;
        }

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateProtocolPdf($formId, $hcNumber, $mode, $page);
        } catch (\Throwable $e) {
            Log::error('reporting.read.protocol_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'modo' => $mode,
                'pagina' => $page,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF del protocolo.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar el protocolo solicitado.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.protocol_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'modo' => $mode,
            'pagina' => $page,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    public function informe012BData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->imagenesService->buildInforme012BData($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012b_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data del informe 012B.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró información para el informe 012B.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012b_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-imagenes-012b-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function cobertura012AData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', $request->query('form_id', '')));
        $hcNumber = trim((string) $request->input('hc_number', $request->query('hc_number', '')));
        $examenIdRaw = $request->input('examen_id', $request->query('examen_id'));
        $examenId = is_numeric($examenIdRaw) ? (int) $examenIdRaw : null;
        $selectedItems = $this->parseSelectedItems($request->input('selected_items', $request->query('selected_items', [])));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->imagenesService->buildCobertura012AData($formId, $hcNumber, $examenId, $selectedItems);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012a_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'examen_id' => $examenId,
                'selected_items' => count($selectedItems),
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data de cobertura 012A.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró información para cobertura 012A.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012a_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'examen_id' => $examenId,
            'selected_items' => count($selectedItems),
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-imagenes-012a-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function coberturaData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->coberturaService->buildCoberturaData($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.cobertura_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data de cobertura.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró información para cobertura.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.cobertura_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-cobertura-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function consultaData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $payload = $this->consultaService->buildConsultaReportData($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.consulta_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data de consulta.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if ($payload === []) {
            return response()
                ->json(['error' => 'No se encontró información para la consulta solicitada.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.consulta_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-consulta-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function postSurgeryRestData(Request $request): JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', $request->query('form_id', '')));
        $hcNumber = trim((string) $request->input('hc_number', $request->query('hc_number', '')));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        $restDaysRaw = $request->input('dias_descanso', $request->query('dias_descanso'));
        $restDays = is_numeric($restDaysRaw) ? (int) $restDaysRaw : null;

        $restStartDate = trim((string) $request->input('fecha_inicio_descanso', $request->query('fecha_inicio_descanso', '')));
        if ($restStartDate === '') {
            $restStartDate = null;
        }

        $observaciones = trim((string) $request->input('observaciones', $request->query('observaciones', '')));
        if ($observaciones === '') {
            $observaciones = null;
        }

        try {
            $payload = $this->postSurgeryRestService->buildData($formId, $hcNumber, [
                'dias_descanso' => $restDays,
                'fecha_inicio_descanso' => $restStartDate,
                'observaciones' => $observaciones,
            ]);
        } catch (\Throwable $e) {
            Log::error('reporting.read.post_surgery_rest_data.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'dias_descanso' => $restDays,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo cargar la data del certificado de descanso.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($payload) || $payload === []) {
            return response()
                ->json(['error' => 'No se encontró información para el certificado solicitado.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.post_surgery_rest_data', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'dias_descanso' => $restDays,
        ]);

        return response()
            ->json([
                'data' => $payload,
                'meta' => [
                    'strategy' => 'strangler-v2',
                    'source' => 'reporting-post-surgery-rest-data-v1',
                ],
            ])
            ->header('X-Request-Id', $requestId);
    }

    public function coberturaPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        $variant = (string) (
            $request->query('variant')
            ?? $request->query('document')
            ?? $request->query('tipo')
            ?? 'combined'
        );
        $segments = $this->parseCoberturaSegments($request);

        try {
            $pdf = $this->reportPdfService()->generateCoberturaPdf($formId, $hcNumber, $variant, $segments);
        } catch (\Throwable $e) {
            Log::error('reporting.read.cobertura_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'variant' => $variant,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF de cobertura.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar cobertura.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.cobertura_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'variant' => $variant,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    public function consultaPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateConsultaPdf($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.consulta_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF de consulta.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar la consulta solicitada.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.consulta_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    public function postSurgeryRestPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', $request->query('form_id', '')));
        $hcNumber = trim((string) $request->input('hc_number', $request->query('hc_number', '')));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        $restDaysRaw = $request->input('dias_descanso', $request->query('dias_descanso'));
        $restDays = is_numeric($restDaysRaw) ? (int) $restDaysRaw : null;

        $restStartDate = trim((string) $request->input('fecha_inicio_descanso', $request->query('fecha_inicio_descanso', '')));
        if ($restStartDate === '') {
            $restStartDate = null;
        }

        $observaciones = trim((string) $request->input('observaciones', $request->query('observaciones', '')));
        if ($observaciones === '') {
            $observaciones = null;
        }

        try {
            $pdf = $this->reportPdfService()->generatePostSurgeryRestPdf($formId, $hcNumber, [
                'dias_descanso' => $restDays,
                'fecha_inicio_descanso' => $restStartDate,
                'observaciones' => $observaciones,
            ]);
        } catch (\Throwable $e) {
            Log::error('reporting.read.post_surgery_rest_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'dias_descanso' => $restDays,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF del certificado de descanso.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar el certificado solicitado.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.post_surgery_rest_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'dias_descanso' => $restDays,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    public function informe012BPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateInforme012BPdf($formId, $hcNumber);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012b_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF del informe 012B.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar el informe 012B.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012b_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    public function informe012BPackagePdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', $request->query('form_id', '')));
        $hcNumber = trim((string) $request->input('hc_number', $request->query('hc_number', '')));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateInforme012BPackagePdf([[
                'form_id' => $formId,
                'hc_number' => $hcNumber,
            ]]);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012b_package_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el paquete 012B.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar el paquete 012B.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012b_package_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'items' => 1,
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId, false);
    }

    public function informe012BPackageSelectionPdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $items = $this->parseSelectedItems($request->input('items', $request->query('items', [])));
        if ($items === []) {
            return response()
                ->json(['error' => 'No se recibieron exámenes para el paquete.'], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateInforme012BPackagePdf($items);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012b_package_selection_pdf.error', [
                'request_id' => $requestId,
                'items' => count($items),
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el paquete 012B.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar el paquete 012B.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012b_package_selection_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'items' => count($items),
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId, false);
    }

    public function cobertura012APdf(Request $request): Response|JsonResponse|RedirectResponse
    {
        $requestId = $this->requestId($request);

        if (!LegacySessionAuth::isAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401)->header('X-Request-Id', $requestId);
            }

            return redirect('/auth/login?auth_required=1')->header('X-Request-Id', $requestId);
        }

        $formId = trim((string) $request->input('form_id', $request->query('form_id', '')));
        $hcNumber = trim((string) $request->input('hc_number', $request->query('hc_number', '')));
        $examenIdRaw = $request->input('examen_id', $request->query('examen_id'));
        $examenId = is_numeric($examenIdRaw) ? (int) $examenIdRaw : null;
        $selectedItems = $this->parseSelectedItems($request->input('selected_items', $request->query('selected_items', [])));

        if ($formId === '' || $hcNumber === '') {
            return response()
                ->json([
                    'error' => 'Faltan parámetros obligatorios.',
                    'required' => ['form_id', 'hc_number'],
                ], 422)
                ->header('X-Request-Id', $requestId);
        }

        try {
            $pdf = $this->reportPdfService()->generateCobertura012APdf($formId, $hcNumber, $examenId, $selectedItems);
        } catch (\Throwable $e) {
            Log::error('reporting.read.imagenes_012a_pdf.error', [
                'request_id' => $requestId,
                'form_id' => $formId,
                'hc_number' => $hcNumber,
                'examen_id' => $examenId,
                'selected_items' => count($selectedItems),
                'error' => $e->getMessage(),
            ]);

            return response()
                ->json(['error' => 'No se pudo generar el PDF de cobertura 012A.'], 500)
                ->header('X-Request-Id', $requestId);
        }

        if (!is_array($pdf) || !isset($pdf['content'], $pdf['filename'])) {
            return response()
                ->json(['error' => 'No se encontró información para generar cobertura 012A.'], 404)
                ->header('X-Request-Id', $requestId);
        }

        Log::info('reporting.read.imagenes_012a_pdf', [
            'request_id' => $requestId,
            'user_id' => LegacySessionAuth::userId($request),
            'form_id' => $formId,
            'hc_number' => $hcNumber,
            'examen_id' => $examenId,
            'selected_items' => count($selectedItems),
        ]);

        return $this->pdfResponse((string) $pdf['content'], (string) $pdf['filename'], $requestId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseSelectedItems($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter($raw, static fn($item): bool => is_array($item)));
        }

        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter($decoded, static fn($item): bool => is_array($item)));
    }

    private function requestId(Request $request): string
    {
        $header = trim((string) $request->header('X-Request-Id', ''));
        if ($header !== '') {
            return $header;
        }

        return 'v2-reporting-' . bin2hex(random_bytes(8));
    }

    private function pdfResponse(string $content, string $filename, string $requestId, bool $inline = true): Response
    {
        $disposition = ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Content-Length' => (string) strlen($content),
            'X-Content-Type-Options' => 'nosniff',
            'X-Request-Id' => $requestId,
        ]);
    }

    /**
     * @return array<int, string>|null
     */
    private function parseCoberturaSegments(Request $request): ?array
    {
        $raw = $request->query('pages')
            ?? $request->query('segments')
            ?? $request->query('segment')
            ?? $request->query('page');

        if (is_array($raw)) {
            $segments = [];
            foreach ($raw as $segment) {
                if (!is_string($segment)) {
                    continue;
                }
                $segment = trim($segment);
                if ($segment === '') {
                    continue;
                }
                $segments[] = $segment;
            }

            return $segments !== [] ? $segments : null;
        }

        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/[,\|]+/', $raw);
        if (!is_array($parts)) {
            return null;
        }

        $segments = [];
        foreach ($parts as $segment) {
            if (!is_string($segment)) {
                continue;
            }
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $segments[] = $segment;
        }

        return $segments !== [] ? $segments : null;
    }

    private function reportPdfService(): ReportPdfService
    {
        if ($this->reportPdfService === null) {
            $this->reportPdfService = new ReportPdfService();
        }

        return $this->reportPdfService;
    }
}
