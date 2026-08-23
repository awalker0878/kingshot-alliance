<?php

declare(strict_types=1);

use App\Contexts\Alliance\Content\Http\Controllers\AllianceRulesController;
use App\Contexts\Alliance\Content\Http\Controllers\NoticeReactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session', 'verified', 'alliance.context'])->group(function (): void {
    Route::get('/alliance/rules', [AllianceRulesController::class, 'index'])
        ->name('alliance.rules.index');

    Route::put('/alliance/content/{content}/reaction', [NoticeReactionController::class, 'update'])
        ->whereUlid('content')
        ->name('alliance.content.reaction.update');
    Route::delete('/alliance/content/{content}/reaction', [NoticeReactionController::class, 'destroy'])
        ->whereUlid('content')
        ->name('alliance.content.reaction.destroy');

    Route::middleware('password.confirm')->group(function (): void {
        Route::put('/alliance/rules', [AllianceRulesController::class, 'update'])
            ->name('alliance.rules.update');
    });
});
