<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Providers;

use App\Contexts\Intelligence\Evidence\Console\Commands\EvidenceDiagnosticsCommand;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Contracts\OcrEngine;
use App\Contexts\Intelligence\Evidence\Contracts\SpatialEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Queries\EvidenceReferenceQuery;
use App\Contexts\Intelligence\Evidence\Services\EvidenceClassifierRouter;
use App\Contexts\Intelligence\Evidence\Services\RoutedEvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Services\TesseractOcrEngine;
use Illuminate\Support\ServiceProvider;

final class EvidenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OcrEngine::class, TesseractOcrEngine::class);
        $this->app->bind(EvidenceClassifier::class, EvidenceClassifierRouter::class);
        $this->app->bind(EvidenceExtractor::class, RoutedEvidenceExtractor::class);
        $this->app->bind(EvidenceReferenceLookup::class, EvidenceReferenceQuery::class);
        $this->app->bind(GovernorProgressionEvidenceReferenceLookup::class, EvidenceReferenceQuery::class);
        $this->app->bind(SpatialEvidenceReferenceLookup::class, EvidenceReferenceQuery::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/territory-observations.php'));
        if ($this->app->runningInConsole()) {
            $this->commands([EvidenceDiagnosticsCommand::class]);
        }
    }
}
