<?php

use App\Modules\Mail\Http\Controllers\MailboxController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/mailbox', [MailboxController::class, 'index']);
    Route::get('/mail', [MailboxController::class, 'index']);
    Route::get('/mailbox/feed', [MailboxController::class, 'feed']);
    Route::post('/mailbox/compose', [MailboxController::class, 'compose']);
});
