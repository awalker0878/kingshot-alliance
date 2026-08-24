<?php

declare(strict_types=1);

use App\ReadModels\AllianceAssistant\Http\Controllers\AllianceAssistantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/assistant', [AllianceAssistantController::class, 'index'])
        ->name('assistant.index');
    Route::post('/assistant/ask', [AllianceAssistantController::class, 'ask'])
        ->middleware('throttle:alliance-assistant')
        ->name('assistant.ask');
});
