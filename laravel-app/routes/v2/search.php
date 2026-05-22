<?php

use App\Modules\Search\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['app.auth'])->group(function (): void {
    Route::get('/search', [SearchController::class, 'index']);
    Route::post('/search/history/clear', [SearchController::class, 'clearHistory']);
});
