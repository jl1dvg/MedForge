<?php

use App\Modules\ControlCenter\Http\Controllers\ControlCenterApiController;
use App\Modules\ControlCenter\Http\Controllers\ControlCenterUiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth', 'app.permission:control_center.view'])->group(function (): void {
    Route::get('/v2/control-center', [ControlCenterUiController::class, 'index']);
    Route::get('/v2/control-center/overview', [ControlCenterApiController::class, 'overview']);
    Route::get('/v2/control-center/organizations', [ControlCenterApiController::class, 'organizations']);
    Route::get('/v2/control-center/organizations/{id}', [ControlCenterApiController::class, 'organization'])->whereNumber('id');
    Route::get('/v2/control-center/instances', [ControlCenterApiController::class, 'instances']);
    Route::get('/v2/control-center/instances/{id}', [ControlCenterApiController::class, 'instance'])->whereNumber('id');
    Route::get('/v2/control-center/instances/{id}/features', [ControlCenterApiController::class, 'features'])->whereNumber('id');
    Route::get('/v2/control-center/services', [ControlCenterApiController::class, 'services']);
    Route::get('/v2/control-center/plans', [ControlCenterApiController::class, 'plans']);
    Route::get('/v2/control-center/deployments', [ControlCenterApiController::class, 'deployments']);
    Route::get('/v2/control-center/usage', [ControlCenterApiController::class, 'usage']);
});

Route::middleware(['app.auth', 'app.permission:control_center.clients.manage'])->group(function (): void {
    Route::post('/v2/control-center/organizations', [ControlCenterApiController::class, 'createOrganization']);
    Route::patch('/v2/control-center/organizations/{id}', [ControlCenterApiController::class, 'updateOrganization'])->whereNumber('id');
    Route::post('/v2/control-center/instances', [ControlCenterApiController::class, 'createInstance']);
    Route::patch('/v2/control-center/instances/{id}', [ControlCenterApiController::class, 'updateInstance'])->whereNumber('id');
    Route::post('/v2/control-center/instances/{id}/rotate-telemetry-token', [ControlCenterApiController::class, 'rotateTelemetryToken'])->whereNumber('id');
    Route::post('/v2/control-center/services', [ControlCenterApiController::class, 'createService']);
    Route::patch('/v2/control-center/services/{id}', [ControlCenterApiController::class, 'updateService'])->whereNumber('id');
});

Route::middleware(['app.auth', 'app.permission:control_center.licenses.manage'])->group(function (): void {
    Route::post('/v2/control-center/plans', [ControlCenterApiController::class, 'createPlan']);
    Route::patch('/v2/control-center/plans/{id}', [ControlCenterApiController::class, 'updatePlan'])->whereNumber('id');
    Route::post('/v2/control-center/contracts', [ControlCenterApiController::class, 'createContract']);
    Route::patch('/v2/control-center/contracts/{id}', [ControlCenterApiController::class, 'updateContract'])->whereNumber('id');
});

Route::middleware(['app.auth', 'app.permission:control_center.state.manage'])->group(function (): void {
    Route::post('/v2/control-center/instances/{id}/state', [ControlCenterApiController::class, 'changeState'])->whereNumber('id');
});

Route::middleware(['app.auth', 'app.permission:control_center.features.manage'])->group(function (): void {
    Route::post('/v2/control-center/instances/{id}/features', [ControlCenterApiController::class, 'updateFeatures'])->whereNumber('id');
});

Route::middleware(['app.auth', 'app.permission:control_center.audit.view'])->group(function (): void {
    Route::get('/v2/control-center/audit', [ControlCenterApiController::class, 'audit']);
});

Route::post('/v2/control-center/telemetry/heartbeat', [ControlCenterApiController::class, 'telemetryHeartbeat']);
Route::post('/v2/control-center/telemetry/debug-headers', [ControlCenterApiController::class, 'telemetryDebugHeaders']);
