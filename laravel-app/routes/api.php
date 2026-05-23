<?php

use App\Modules\Consultas\Http\Controllers\ConsultasReadController;
use App\Modules\Consultas\Http\Controllers\ConsultasWriteController;
use App\Modules\Pacientes\Services\PacientesFlujoService;
use App\Modules\Solicitudes\Http\Controllers\SolicitudesWriteController;
use App\Modules\Whatsapp\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->group(function (): void {
    require __DIR__ . '/v2/health.php';
    require __DIR__ . '/v2/dashboard.php';
    require __DIR__ . '/v2/reporting.php';
    require __DIR__ . '/v2/pacientes.php';
    require __DIR__ . '/v2/agenda.php';
    require __DIR__ . '/v2/billing.php';
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/cirugias.php';
    });
    require __DIR__ . '/v2/derivaciones.php';
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/solicitudes.php';
    });
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/examenes.php';
    });
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/identity_verification.php';
    });
    require __DIR__ . '/v2/consultas.php';
    require __DIR__ . '/v2/crm.php';
    require __DIR__ . '/v2/codes.php';
    require __DIR__ . '/v2/auth.php';
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/settings.php';
    });
    Route::middleware('web')->group(function (): void {
        require __DIR__ . '/v2/whatsapp.php';
    });
});

Route::middleware(['consultas.cors'])->group(function (): void {
    foreach ([
        '/consultas/guardar',
        '/consultas/guardar.php',
        '/api/consultas/guardar',
        '/api/consultas/guardar.php',
    ] as $path) {
        Route::options($path, static fn () => response('', 204));
        Route::post($path, static function (Request $request, ConsultasWriteController $controller) {
            return $controller->guardar($request);
        });
    }

    foreach ([
        '/consultas/anterior',
        '/consultas/anterior.php',
        '/api/consultas/anterior',
        '/api/consultas/anterior.php',
    ] as $path) {
        Route::options($path, static fn () => response('', 204));
        Route::get($path, static function (Request $request, ConsultasReadController $controller) {
            return $controller->anterior($request);
        });
    }

    foreach ([
        '/api/solicitudes/estado',
        '/api/solicitudes/estado.php',
    ] as $path) {
        Route::options($path, static fn () => response('', 204));
        Route::get($path, static function (Request $request, SolicitudesWriteController $controller) {
            return $controller->apiEstadoGet($request);
        });
        Route::post($path, static function (Request $request, SolicitudesWriteController $controller) {
            return $controller->apiEstadoPost($request);
        });
    }

    foreach ([
        '/api/proyecciones/consulta.php' => [
            'CONSULTA' => 'en_proceso',
            'CONSULTA_TERMINADO' => 'terminado_sin_dilatar',
            'DILATAR' => 'terminado_dilatar',
        ],
        '/api/proyecciones/optometria.php' => [
            'OPTOMETRIA' => 'en_proceso',
            'OPTOMETRIA_TERMINADO' => 'terminado_sin_dilatar',
            'DILATAR' => 'terminado_dilatar',
        ],
    ] as $path => $stateMap) {
        Route::options($path, static fn () => response('', 204));

        Route::get($path, static function (Request $request) use ($stateMap) {
            if ($request->query('action') !== 'estado') {
                return response()->json(['success' => false, 'message' => 'Acción no soportada'], 422);
            }

            $formId = trim((string) $request->query('form_id', ''));
            if ($formId === '') {
                return response()->json(['success' => false, 'message' => 'form_id requerido'], 422);
            }

            $estadoBd = DB::table('procedimiento_proyectado')
                ->where('form_id', $formId)
                ->whereRaw('COALESCE(sigcenter_present, 1) = 1')
                ->value('estado_agenda');

            return response()->json([
                'success' => true,
                'estado' => $stateMap[(string) $estadoBd] ?? 'pendiente',
                'estado_bd' => $estadoBd,
            ]);
        });

        Route::post($path, static function (Request $request) use ($stateMap) {
            $formId = trim((string) $request->input('form_id', ''));
            $estado = trim((string) $request->input('estado', ''));
            $frontToDb = array_flip($stateMap);
            $targetState = $frontToDb[$estado] ?? null;

            if ($targetState === null) {
                return response()->json(['success' => false, 'message' => 'Estado inválido proporcionado.'], 422);
            }

            $service = new PacientesFlujoService(DB::connection()->getPdo());
            $result = $service->actualizarEstadoTrayecto($formId, $targetState);
            return response()->json($result, !empty($result['success']) ? 200 : 422);
        });
    }
});

Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/mail.php';
});

// /mail-templates routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/mail_templates.php';
});

// /ai routes intentionally outside /v2 prefix — matches legacy paths
require __DIR__ . '/v2/ai.php';

// /kpis routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/kpi.php';
});

// /search routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/search.php';
});

// /insumos routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/insumos.php';
});

// /doctores routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/doctores.php';
});

// /cron-manager routes intentionally outside /v2 prefix — matches legacy paths
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/cron_manager.php';
});

// /api/cive-extension routes — consumed by Chrome extension (asistentecive.consulmed.me)
Route::middleware('web')->group(function (): void {
    require __DIR__ . '/v2/cive_extension.php';
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

// /cirugias and /pacientes are now intercepted by the Laravel bridge (Onda 4).
// Redirect non-prefixed paths to their canonical /v2/ equivalents so bookmarks
// and legacy links still work.
foreach (['/cirugias', '/pacientes'] as $module) {
    Route::match(
        ['GET', 'POST'],
        $module . '{path?}',
        static function (Request $request, string $path = '') use ($legacyRedirect, $module) {
            $method = $request->method();
            $status = $method === 'POST' ? 307 : 302;
            return $legacyRedirect($request, '/v2' . $module . $path, $status);
        }
    )->where('path', '(/.+)?');
}
