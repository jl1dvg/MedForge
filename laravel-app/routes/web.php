<?php

use App\Modules\Dashboard\Http\Controllers\DashboardUiController;
use App\Modules\Consultas\Http\Controllers\ConsultasUiController;
use App\Modules\Cirugias\Http\Controllers\ProtocolosLegacyBridgeController;
use App\Modules\Examenes\Http\Controllers\ExamenesUiController;
use App\Modules\Examenes\Http\Controllers\ImagenesUiController;
use App\Modules\Farmacia\Http\Controllers\FarmaciaUiController;
use App\Modules\Auth\Http\Controllers\LoginController;
use App\Modules\Auth\Http\Controllers\UnifiedLogoutController;
use App\Modules\CRM\Http\Controllers\CrmProposalController;
use App\Modules\CRM\Http\Controllers\CrmUiController;
use App\Modules\Codes\Http\Controllers\CodesUiController;
use App\Modules\Codes\Http\Controllers\CodesWriteController;
use App\Modules\Derivaciones\Http\Controllers\DerivacionesUiController;
use App\Modules\Shared\Http\Controllers\FeedbackUiController;
use App\Modules\Solicitudes\Http\Controllers\SolicitudesUiController;
use App\Modules\Settings\Http\Controllers\SettingsUiController;
use App\Modules\Shared\Http\Controllers\FeedbackWriteController;
use App\Modules\Usuarios\Http\Controllers\RolesUiController;
use App\Modules\Usuarios\Http\Controllers\UsuariosUiController;
use App\Modules\Whatsapp\Http\Controllers\WhatsappUiController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/login', [LoginController::class, 'show'])->name('login');
Route::post('/auth/login', [LoginController::class, 'login']);
Route::get('/auth/logout', [UnifiedLogoutController::class, 'logout']);
Route::get('/v2/auth/logout', [UnifiedLogoutController::class, 'logout']);
Route::get('/proposal/{id}/{hash}', [CrmProposalController::class, 'publicView'])->whereNumber('id');
Route::get('/proposal/{id}/{hash}/pdf', [CrmProposalController::class, 'publicPdf'])->whereNumber('id');

Route::middleware(['app.auth'])->group(function (): void {
    Route::post('/feedback/api/report', [FeedbackWriteController::class, 'store']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,settings.manage,settings.view'])->group(function (): void {
    Route::get('/v2/feedback', [FeedbackUiController::class, 'index']);
    Route::post('/v2/feedback/{id}/status', [FeedbackUiController::class, 'updateStatus'])->whereNumber('id');
});

Route::middleware(['app.auth', 'app.permission:administrativo,dashboard.view'])->group(function (): void {
    Route::get('/v2/dashboard', [DashboardUiController::class, 'index']);
    Route::get('/v2/dashboard-v3', [DashboardUiController::class, 'indexV3']);
    Route::get('/v3/dashboard', [DashboardUiController::class, 'indexV3']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,derivaciones.view,pacientes.view,solicitudes.view'])->group(function (): void {
    Route::get('/v2/derivaciones', [DerivacionesUiController::class, 'index']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,solicitudes.view,solicitudes.update,solicitudes.manage'])->group(function (): void {
    Route::get('/v2/solicitudes', [SolicitudesUiController::class, 'index']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,solicitudes.dashboard.view,solicitudes.view,solicitudes.update,solicitudes.manage'])->group(function (): void {
    Route::get('/v2/solicitudes/dashboard', [SolicitudesUiController::class, 'dashboard']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,solicitudes.turnero,solicitudes.update,solicitudes.manage,solicitudes.view'])->group(function (): void {
    Route::get('/v2/solicitudes/turnero', [SolicitudesUiController::class, 'turnero']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,examenes.view,examenes.manage'])->group(function (): void {
    Route::get('/v2/examenes', [ExamenesUiController::class, 'index']);
    Route::get('/v2/examenes/turnero', [ExamenesUiController::class, 'turnero']);
    Route::get('/v2/imagenes/examenes-realizados', [ImagenesUiController::class, 'realizadas']);
    Route::get('/v2/imagenes/dashboard', [ImagenesUiController::class, 'dashboard']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,agenda.view,pacientes.view,solicitudes.view,examenes.view'])->group(function (): void {
    Route::get('/v2/consultas', [ConsultasUiController::class, 'edit']);
    Route::post('/v2/consultas', [ConsultasUiController::class, 'update']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,protocolos.manage,protocolos.templates.view,protocolos.templates.manage'])->group(function (): void {
    Route::get('/v2/protocolos', [ProtocolosLegacyBridgeController::class, 'index']);
    Route::get('/v2/protocolos/crear', [ProtocolosLegacyBridgeController::class, 'create']);
    Route::get('/v2/protocolos/editar', [ProtocolosLegacyBridgeController::class, 'edit']);
    Route::post('/v2/protocolos/guardar', [ProtocolosLegacyBridgeController::class, 'store']);
    Route::post('/v2/protocolos/eliminar', [ProtocolosLegacyBridgeController::class, 'delete']);

    Route::get('/protocolos', function (\Illuminate\Http\Request $request) {
        $target = '/v2/protocolos';
        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?' . $query;
        }
        return redirect($target, 302);
    });
    Route::get('/protocolos/crear', function (\Illuminate\Http\Request $request) {
        $target = '/v2/protocolos/crear';
        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?' . $query;
        }
        return redirect($target, 302);
    });
    Route::get('/protocolos/editar', function (\Illuminate\Http\Request $request) {
        $target = '/v2/protocolos/editar';
        $query = $request->getQueryString();
        if (is_string($query) && $query !== '') {
            $target .= '?' . $query;
        }
        return redirect($target, 302);
    });

    // 307 preserves method/body for transitional POST callers.
    Route::post('/protocolos/guardar', static fn () => redirect('/v2/protocolos/guardar', 307));
    Route::post('/protocolos/eliminar', static fn () => redirect('/v2/protocolos/eliminar', 307));
});

Route::middleware(['app.auth', 'app.permission:administrativo,farmacia.view,insumos.view,insumos.manage'])->group(function (): void {
    Route::get('/v2/farmacia', [FarmaciaUiController::class, 'dashboard']);
    Route::get('/v2/farmacia/export/pdf', [FarmaciaUiController::class, 'exportPdf']);
    Route::get('/v2/farmacia/export/excel', [FarmaciaUiController::class, 'exportExcel']);
});

$registerUsuariosReadRoutes = static function (string $basePath): void {
    Route::get($basePath, [UsuariosUiController::class, 'index']);
};

$registerUsuariosWriteRoutes = static function (string $basePath): void {
    Route::get($basePath . '/create', [UsuariosUiController::class, 'create']);
    Route::post($basePath, [UsuariosUiController::class, 'store']);
    Route::get($basePath . '/{id}/edit', [UsuariosUiController::class, 'edit'])->whereNumber('id');
    Route::post($basePath . '/{id}', [UsuariosUiController::class, 'update'])->whereNumber('id');
    Route::post($basePath . '/{id}/delete', [UsuariosUiController::class, 'destroy'])->whereNumber('id');
};

$registerRolesReadRoutes = static function (string $basePath): void {
    Route::get($basePath, [RolesUiController::class, 'index']);
};

$registerRolesWriteRoutes = static function (string $basePath): void {
    Route::get($basePath . '/create', [RolesUiController::class, 'create']);
    Route::post($basePath, [RolesUiController::class, 'store']);
    Route::get($basePath . '/{id}/edit', [RolesUiController::class, 'edit'])->whereNumber('id');
    Route::post($basePath . '/{id}', [RolesUiController::class, 'update'])->whereNumber('id');
    Route::post($basePath . '/{id}/delete', [RolesUiController::class, 'destroy'])->whereNumber('id');
};

Route::middleware(['app.auth', 'app.permission:administrativo,admin.usuarios.view,admin.usuarios.manage,admin.usuarios'])->group(function () use ($registerUsuariosReadRoutes): void {
    foreach (['/usuarios', '/v2/usuarios'] as $basePath) {
        $registerUsuariosReadRoutes($basePath);
    }
});

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/usuarios/media', [UsuariosUiController::class, 'media']);
    Route::get('/v2/usuarios/media', [UsuariosUiController::class, 'media']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,admin.usuarios.manage,admin.usuarios'])->group(function () use ($registerUsuariosWriteRoutes): void {
    foreach (['/usuarios', '/v2/usuarios'] as $basePath) {
        $registerUsuariosWriteRoutes($basePath);
    }
});

Route::middleware(['app.auth', 'app.permission:administrativo,admin.roles.view,admin.roles.manage,admin.roles'])->group(function () use ($registerRolesReadRoutes): void {
    foreach (['/roles', '/v2/roles'] as $basePath) {
        $registerRolesReadRoutes($basePath);
    }
});

Route::middleware(['app.auth', 'app.permission:administrativo,admin.roles.manage,admin.roles'])->group(function () use ($registerRolesWriteRoutes): void {
    foreach (['/roles', '/v2/roles'] as $basePath) {
        $registerRolesWriteRoutes($basePath);
    }
});

Route::middleware(['app.auth', 'app.permission:administrativo,settings.manage,settings.view'])->group(function (): void {
    Route::get('/settings', function (\Illuminate\Http\Request $request) {
        $query = $request->query();
        $target = '/v2/settings';
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        return redirect($target);
    });
    Route::post('/settings', [SettingsUiController::class, 'save']);
    Route::get('/v2/settings', [SettingsUiController::class, 'index']);
    Route::post('/v2/settings', [SettingsUiController::class, 'save']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,codes.view,codes.manage'])->group(function (): void {
    Route::get('/v2/codes', [CodesUiController::class, 'index']);
});

Route::middleware(['app.auth', 'app.permission:administrativo,codes.manage'])->group(function (): void {
    Route::get('/v2/codes/create', [CodesUiController::class, 'create']);
    Route::get('/v2/codes/import', [CodesUiController::class, 'import']);
    Route::post('/v2/codes/import', [CodesWriteController::class, 'import']);
    Route::post('/v2/codes/deduplicate', [CodesWriteController::class, 'deduplicate']);
    Route::post('/v2/codes', [CodesWriteController::class, 'store']);
    Route::get('/v2/codes/{id}/edit', [CodesUiController::class, 'edit'])->whereNumber('id');
    Route::post('/v2/codes/{id}', [CodesWriteController::class, 'update'])->whereNumber('id');
    Route::post('/v2/codes/{id}/delete', [CodesWriteController::class, 'destroy'])->whereNumber('id');
    Route::post('/v2/codes/{id}/toggle', [CodesWriteController::class, 'toggleActive'])->whereNumber('id');
    Route::post('/v2/codes/{id}/relate', [CodesWriteController::class, 'addRelation'])->whereNumber('id');
    Route::post('/v2/codes/{id}/relate/del', [CodesWriteController::class, 'removeRelation'])->whereNumber('id');
    Route::get('/v2/codes/packages', [CodesUiController::class, 'packages']);
});

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/v2/whatsapp', [WhatsappUiController::class, 'hub'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,whatsapp.templates.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/v2/whatsapp/chat', [WhatsappUiController::class, 'chat'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/chat');
    Route::get('/v2/whatsapp/chat-v3', [WhatsappUiController::class, 'chatV3'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/chat');
    Route::get('/v3/whatsapp/chat', [WhatsappUiController::class, 'chatV3'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/chat');
    Route::get('/v2/whatsapp/campaigns', [WhatsappUiController::class, 'campaigns'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/campaigns');
    Route::get('/v2/whatsapp/templates', [WhatsappUiController::class, 'templates'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/templates');
    Route::get('/v2/whatsapp/dashboard', [WhatsappUiController::class, 'dashboard'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/dashboard');
    Route::get('/v2/whatsapp/flowmaker', [WhatsappUiController::class, 'flowmaker'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/flowmaker');
    Route::get('/v2/whatsapp/kb', [WhatsappUiController::class, 'knowledgeBase'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/flowmaker');
    Route::get('/v2/whatsapp/ai-agent', [WhatsappUiController::class, 'aiAgent'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/flowmaker');
    Route::get('/v2/whatsapp/leads', [WhatsappUiController::class, 'leads'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.supervise,whatsapp.chat.assign,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/chat');
    // Legacy UI paths — serve the same controllers once /whatsapp bridge is active
    Route::get('/whatsapp/chat', [WhatsappUiController::class, 'chat'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/chat');
    Route::get('/whatsapp/templates', [WhatsappUiController::class, 'templates'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/templates');
    Route::get('/whatsapp/dashboard', [WhatsappUiController::class, 'dashboard'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.supervise,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/dashboard');
    Route::get('/whatsapp/flowmaker', [WhatsappUiController::class, 'flowmaker'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/flowmaker');
});

Route::middleware(['app.auth', 'app.permission:administrativo,crm.view,crm.manage'])->group(function (): void {
    Route::get('/v2/crm', [CrmUiController::class, 'index']);
});

Route::redirect('/crm', '/v2/crm');
Route::redirect('/leads', '/v2/crm');
