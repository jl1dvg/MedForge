<?php

use App\Modules\Whatsapp\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function (): void {
    require __DIR__ . '/v2/health.php';
    require __DIR__ . '/v2/dashboard.php';
    require __DIR__ . '/v2/reporting.php';
    require __DIR__ . '/v2/pacientes.php';
    require __DIR__ . '/v2/agenda.php';
    require __DIR__ . '/v2/billing.php';
    require __DIR__ . '/v2/cirugias.php';
    require __DIR__ . '/v2/derivaciones.php';
    require __DIR__ . '/v2/solicitudes.php';
    require __DIR__ . '/v2/examenes.php';
    require __DIR__ . '/v2/consultas.php';
    require __DIR__ . '/v2/crm.php';
    require __DIR__ . '/v2/codes.php';
    require __DIR__ . '/v2/auth.php';
    require __DIR__ . '/v2/whatsapp.php';
});

Route::middleware([
    'whatsapp.feature:webhook,/whatsapp/webhook',
])->group(function (): void {
    Route::get('/whatsapp/webhook', [WebhookController::class, 'verify']);
    Route::post('/whatsapp/webhook', [WebhookController::class, 'receive']);
    Route::get('/v2/whatsapp/webhook', [WebhookController::class, 'verify']);
    Route::post('/v2/whatsapp/webhook', [WebhookController::class, 'receive']);
});

$legacyRedirect = static function (Request $request, string $target, int $status = 302) {
    $query = trim((string) $request->getQueryString());
    if ($query !== '') {
        $separator = str_contains($target, '?') ? '&' : '?';
        $target .= $separator . $query;
    }

    return redirect($target, $status);
};

$legacyCoberturaQueueTarget = static function (Request $request): ?string {
    $formId = trim((string) $request->query('form_id', ''));
    $hcNumber = trim((string) $request->query('hc_number', ''));
    $variant = trim((string) $request->query('variant', 'template'));
    if ($variant === '') {
        $variant = 'template';
    }

    if ($formId === '' || $hcNumber === '') {
        return null;
    }

    return '/v2/reports/cobertura/pdf?' . http_build_query([
        'form_id' => $formId,
        'hc_number' => $hcNumber,
        'variant' => $variant,
    ], '', '&', PHP_QUERY_RFC3986);
};

Route::get('/reports/protocolo/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/protocolo/pdf'));
Route::get('/reports/cobertura/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/cobertura/pdf'));
Route::get('/reports/cobertura/pdf-template', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/cobertura/pdf?variant=template'));
Route::get('/reports/cobertura/pdf-html', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/cobertura/pdf?variant=appendix'));
Route::get('/reports/cobertura/pdf-queue', static function (Request $request) use ($legacyCoberturaQueueTarget) {
    $target = $legacyCoberturaQueueTarget($request);
    if ($target === null) {
        return response()->json([
            'error' => 'Faltan parámetros obligatorios.',
            'required' => ['form_id', 'hc_number'],
        ], 400);
    }

    $jobId = 'v2-' . bin2hex(random_bytes(8));
    $statusUrl = '/reports/cobertura/pdf-queue/status?' . http_build_query([
        'id' => $jobId,
        'target' => $target,
    ], '', '&', PHP_QUERY_RFC3986);

    return response()->json([
        'ok' => true,
        'strategy' => 'strangler-v2',
        'job_id' => $jobId,
        'status_url' => $statusUrl,
    ], 202);
});
Route::get('/reports/cobertura/pdf-queue/status', static function (Request $request) {
    $id = trim((string) $request->query('id', ''));
    $target = trim((string) $request->query('target', ''));

    if ($id === '') {
        return response()->json([
            'error' => 'Parámetro id inválido.',
        ], 400);
    }

    if ($target === '' || !str_starts_with($target, '/v2/reports/cobertura/pdf')) {
        return response()->json([
            'error' => 'Parámetro target inválido.',
            'required' => ['target'],
        ], 400);
    }

    return response()->json([
        'ok' => true,
        'job' => [
            'id' => $id,
            'status' => 'completed',
            'strategy' => 'strangler-v2',
            'progress' => 100,
            'download_url' => $target,
        ],
    ]);
});
Route::get('/reports/consulta/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/consulta/pdf'));
Route::match(['GET', 'POST'], '/reports/cirugias/descanso/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/cirugias/descanso/pdf', 307));

Route::get('/imagenes/informes/012b/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/imagenes/012b/pdf'));
Route::get('/imagenes/informes/012b/paquete', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/imagenes/012b/paquete'));
Route::match(['GET', 'POST'], '/imagenes/informes/012b/paquete/seleccion', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/imagenes/012b/paquete/seleccion', 307));
Route::get('/examenes/cobertura-012a/pdf', static fn(Request $request) => $legacyRedirect($request, '/v2/reports/imagenes/012a/pdf'));
