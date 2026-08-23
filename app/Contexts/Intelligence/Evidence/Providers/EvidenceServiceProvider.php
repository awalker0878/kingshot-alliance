<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Providers;

use App\Contexts\Intelligence\Evidence\Console\Commands\EvidenceDiagnosticsCommand;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Contracts\OcrEngine;
use App\Contexts\Intelligence\Evidence\Services\BearHuntBattleReportExtractor;
use App\Contexts\Intelligence\Evidence\Services\BearHuntEvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Services\TesseractOcrEngine;
use Illuminate\Support\ServiceProvider;

final class EvidenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrEngine::class, TesseractOcrEngine::class);
        $this->app->bind(EvidenceClassifier::class, BearHuntEvidenceClassifier::class);
        $this->app->bind(EvidenceExtractor::class, BearHuntBattleReportExtractor::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([EvidenceDiagnosticsCommand::class]);
        }
    }
}
