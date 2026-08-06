<?php

declare(strict_types=1);

use App\Http\Controllers\Health\ReadinessController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', static fn () => Inertia::render('Home', [
    'application' => [
        'name' => config('app.name'),
        'environment' => app()->environment(),
        'version' => config('operations.version'),
        'releaseSha' => config('operations.release_sha'),
    ],
]))->name('home');

Route::get('/health/ready', ReadinessController::class)
    ->name('health.ready');
