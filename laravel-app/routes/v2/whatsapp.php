<?php

use App\Modules\Whatsapp\Http\Controllers\ConversationReadController;
use App\Modules\Whatsapp\Http\Controllers\ConversationOpsController;
use App\Modules\Whatsapp\Http\Controllers\ConversationWriteController;
use App\Modules\Whatsapp\Http\Controllers\CampaignReadController;
use App\Modules\Whatsapp\Http\Controllers\CampaignWriteController;
use App\Modules\Whatsapp\Http\Controllers\FlowmakerReadController;
use App\Modules\Whatsapp\Http\Controllers\FlowmakerWriteController;
use App\Modules\Whatsapp\Http\Controllers\KpiReadController;
use App\Modules\Whatsapp\Http\Controllers\MediaReadController;
use App\Modules\Whatsapp\Http\Controllers\MediaWriteController;
use App\Modules\Whatsapp\Http\Controllers\ProductivityReadController;
use App\Modules\Whatsapp\Http\Controllers\ProductivityWriteController;
use App\Modules\Whatsapp\Http\Controllers\TemplateReadController;
use App\Modules\Whatsapp\Http\Controllers\TemplateWriteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.view,whatsapp.templates.manage,settings.manage',
    'whatsapp.feature:api-read,/whatsapp/api/conversations',
])->prefix('/whatsapp/api')->group(function (): void {
    Route::get('/conversations', [ConversationReadController::class, 'index']);
    Route::get('/conversations/{conversationId}', [ConversationReadController::class, 'show'])->whereNumber('conversationId');
    Route::get('/contacts/search', [ConversationWriteController::class, 'searchContacts']);
    Route::get('/messages/{messageId}/media', [MediaReadController::class, 'download'])->whereNumber('messageId');
    Route::get('/campaigns', [CampaignReadController::class, 'index']);
    Route::get('/campaigns/audience-suggestions', [CampaignReadController::class, 'audienceSuggestions']);
    Route::get('/agents', [ConversationOpsController::class, 'listAgents']);
    Route::get('/agents/summary', [ConversationOpsController::class, 'agentSummary']);
    Route::get('/presence', [ConversationOpsController::class, 'getPresence']);
    Route::get('/quick-replies', [ProductivityReadController::class, 'quickReplies']);
    Route::get('/conversations/{conversationId}/notes', [ProductivityReadController::class, 'conversationNotes'])->whereNumber('conversationId');
    Route::get('/kpis', [KpiReadController::class, 'index']);
    Route::get('/kpis/drilldown', [KpiReadController::class, 'drilldown']);
    Route::get('/kpis/export', [KpiReadController::class, 'export']);
    Route::get('/flowmaker/contract', [FlowmakerReadController::class, 'contract']);
    Route::get('/flowmaker/simulate', [FlowmakerReadController::class, 'simulate']);
    Route::get('/flowmaker/compare', [FlowmakerReadController::class, 'compare']);
    Route::get('/flowmaker/shadow-runs', [FlowmakerReadController::class, 'shadowRuns']);
    Route::get('/flowmaker/shadow-summary', [FlowmakerReadController::class, 'shadowSummary']);
    Route::get('/flowmaker/readiness', [FlowmakerReadController::class, 'readiness']);
    Route::get('/templates', [TemplateReadController::class, 'index']);
    Route::get('/templates/sync', [TemplateWriteController::class, 'syncLanding']);
});

Route::middleware([
    'legacy.auth',
    'legacy.permission:administrativo,whatsapp.manage,whatsapp.chat.send,whatsapp.templates.manage,settings.manage',
    'whatsapp.feature:api-write,/whatsapp/chat',
])->prefix('/whatsapp/api')->group(function (): void {
    Route::post('/conversations/{conversationId}/messages', [ConversationWriteController::class, 'sendMessage'])->whereNumber('conversationId');
    Route::post('/conversations/start-template', [ConversationWriteController::class, 'startWithTemplate']);
    Route::post('/media/upload', [MediaWriteController::class, 'upload']);
    Route::post('/campaigns', [CampaignWriteController::class, 'store']);
    Route::post('/campaigns/{campaignId}/dry-run', [CampaignWriteController::class, 'dryRun'])->whereNumber('campaignId');
    Route::post('/conversations/{conversationId}/notes', [ProductivityWriteController::class, 'storeConversationNote'])->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/assign', [ConversationOpsController::class, 'assign'])->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/transfer', [ConversationOpsController::class, 'transfer'])->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/queue-by-role', [ConversationOpsController::class, 'queueByRole'])->whereNumber('conversationId');
    Route::post('/conversations/{conversationId}/close', [ConversationOpsController::class, 'close'])->whereNumber('conversationId');
    Route::post('/presence', [ConversationOpsController::class, 'updatePresence']);
    Route::post('/handoffs/requeue-expired', [ConversationOpsController::class, 'requeueExpired']);
    Route::post('/flowmaker/publish', [FlowmakerWriteController::class, 'publish']);
    Route::post('/quick-replies', [ProductivityWriteController::class, 'storeQuickReply']);
    Route::post('/templates', [TemplateWriteController::class, 'store']);
    Route::post('/templates/clone', [TemplateWriteController::class, 'clone']);
    Route::post('/templates/{templateId}', [TemplateWriteController::class, 'update'])->whereNumber('templateId');
    Route::post('/templates/{templateId}/publish', [TemplateWriteController::class, 'publish'])->whereNumber('templateId');
    Route::post('/templates/sync', [TemplateWriteController::class, 'sync']);
});
