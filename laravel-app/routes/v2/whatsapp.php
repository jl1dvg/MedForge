<?php

use App\Modules\Whatsapp\Http\Controllers\ConversationReadController;
use App\Modules\Whatsapp\Http\Controllers\ConversationOpsController;
use App\Modules\Whatsapp\Http\Controllers\ConversationWriteController;
use App\Modules\Whatsapp\Http\Controllers\CampaignReadController;
use App\Modules\Whatsapp\Http\Controllers\CampaignWriteController;
use App\Modules\Whatsapp\Http\Controllers\FlowmakerReadController;
use App\Modules\Whatsapp\Http\Controllers\FlowmakerWriteController;
use App\Modules\Whatsapp\Http\Controllers\KpiReadController;
use App\Modules\Whatsapp\Http\Controllers\KnowledgeBaseReadController;
use App\Modules\Whatsapp\Http\Controllers\KnowledgeBaseWriteController;
use App\Modules\Whatsapp\Http\Controllers\MediaReadController;
use App\Modules\Whatsapp\Http\Controllers\MediaWriteController;
use App\Modules\Whatsapp\Http\Controllers\ProductivityReadController;
use App\Modules\Whatsapp\Http\Controllers\ProductivityWriteController;
use App\Modules\Whatsapp\Http\Controllers\TemplateReadController;
use App\Modules\Whatsapp\Http\Controllers\TemplateWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'whatsapp.feature:api-read,/whatsapp/api/conversations',
])->prefix('/whatsapp/api')->group(function (): void {
    Route::get('/conversations', [ConversationReadController::class, 'index'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage');
    Route::get('/conversations/{conversationId}', [ConversationReadController::class, 'show'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::get('/contacts/search', [ConversationWriteController::class, 'searchContacts'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,settings.manage');
    Route::get('/messages/{messageId}/media', [MediaReadController::class, 'download'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('messageId');
    Route::get('/campaigns', [CampaignReadController::class, 'index'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage');
    Route::get('/campaigns/audience-suggestions', [CampaignReadController::class, 'audienceSuggestions'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage');
    Route::get('/agents', [ConversationOpsController::class, 'listAgents'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage');
    Route::get('/agents/summary', [ConversationOpsController::class, 'agentSummary'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.supervise,settings.manage');
    Route::get('/presence', [ConversationOpsController::class, 'getPresence'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage');
    Route::get('/quick-replies', [ProductivityReadController::class, 'quickReplies'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,settings.manage');
    Route::get('/conversations/{conversationId}/notes', [ProductivityReadController::class, 'conversationNotes'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::get('/kpis', [KpiReadController::class, 'index'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.supervise,settings.manage');
    Route::get('/kpis/drilldown', [KpiReadController::class, 'drilldown'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.supervise,settings.manage');
    Route::get('/kpis/export', [KpiReadController::class, 'export'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.supervise,settings.manage');
    Route::get('/flowmaker/contract', [FlowmakerReadController::class, 'contract'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/simulate', [FlowmakerReadController::class, 'simulate'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/compare', [FlowmakerReadController::class, 'compare'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/shadow-runs', [FlowmakerReadController::class, 'shadowRuns'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/shadow-summary', [FlowmakerReadController::class, 'shadowSummary'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/readiness', [FlowmakerReadController::class, 'readiness'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/flowmaker/ai-runs', [FlowmakerReadController::class, 'aiRuns'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/knowledge-base', [KnowledgeBaseReadController::class, 'index'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::get('/templates', [TemplateReadController::class, 'index'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage');
    Route::get('/templates/sync', [TemplateWriteController::class, 'syncLanding'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage');
});

Route::middleware([
    'legacy.auth',
    'whatsapp.feature:api-write,/whatsapp/chat',
])->prefix('/whatsapp/api')->group(function (): void {
    Route::post('/conversations/{conversationId}/messages', [ConversationWriteController::class, 'sendMessage'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/conversations/start-template', [ConversationWriteController::class, 'startWithTemplate'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage');
    Route::post('/media/upload', [MediaWriteController::class, 'upload'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,settings.manage');
    Route::post('/campaigns', [CampaignWriteController::class, 'store'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage');
    Route::post('/campaigns/{campaignId}/dry-run', [CampaignWriteController::class, 'dryRun'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage')
        ->whereNumber('campaignId');
    Route::post('/conversations/{conversationId}/notes', [ProductivityWriteController::class, 'storeConversationNote'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/assign', [ConversationOpsController::class, 'assign'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/transfer', [ConversationOpsController::class, 'transfer'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/queue-by-role', [ConversationOpsController::class, 'queueByRole'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/close', [ConversationOpsController::class, 'close'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.supervise,settings.manage')
        ->whereNumber('conversationId');
    Route::post('/presence', [ConversationOpsController::class, 'updatePresence'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.chat.send,whatsapp.chat.assign,whatsapp.chat.supervise,settings.manage');
    Route::post('/handoffs/requeue-expired', [ConversationOpsController::class, 'requeueExpired'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.supervise,settings.manage');
    Route::post('/flowmaker/publish', [FlowmakerWriteController::class, 'publish'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::post('/knowledge-base', [KnowledgeBaseWriteController::class, 'store'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage');
    Route::post('/quick-replies', [ProductivityWriteController::class, 'storeQuickReply'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,settings.manage');
    Route::post('/templates', [TemplateWriteController::class, 'store'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage');
    Route::post('/templates/clone', [TemplateWriteController::class, 'clone'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage');
    Route::post('/templates/{templateId}', [TemplateWriteController::class, 'update'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage')
        ->whereNumber('templateId');
    Route::post('/templates/{templateId}/publish', [TemplateWriteController::class, 'publish'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage')
        ->whereNumber('templateId');
    Route::post('/templates/sync', [TemplateWriteController::class, 'sync'])
        ->middleware('legacy.permission:administrativo,whatsapp.manage,whatsapp.templates.manage,settings.manage');
});
