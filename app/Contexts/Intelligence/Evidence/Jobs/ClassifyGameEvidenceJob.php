<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Jobs;

use App\Contexts\Intelligence\Evidence\Actions\ClassifyGameEvidence;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class ClassifyGameEvidenceJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $evidenceId) {}

    public function uniqueId(): string
    {
        return $this->evidenceId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('evidence-classify:'.$this->evidenceId))->releaseAfter(30)->expireAfter(180)];
    }

    public function handle(ClassifyGameEvidence $classify): void
    {
        $classify->handle($this->evidenceId);
    }
}
