<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Intelligence\Evidence;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Evidence\Actions\UploadGameEvidence;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Shared\Infrastructure\Uploads\Services\UploadScanner;
use App\Shared\Infrastructure\Uploads\ValueObjects\UploadScanResult;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class EvidenceUploadSecurityV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_exact_duplicates_are_reused_inside_the_event_but_never_disclosed_across_alliances(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('evidence.disk', 'local');
        $this->bindScanner(clean: true);

        $scenario = new ScenarioFactory;
        $firstAccount = $scenario->authUser();
        $firstActor = $scenario->player((int) $firstAccount->id, 59110);
        [$firstAllianceId, $firstOccurrenceId] = $this->bearHunt($scenario, $firstActor);
        $binary = $this->pngBinary();
        $upload = app(UploadGameEvidence::class);

        $first = $upload->handle(
            $firstActor->playerId,
            $firstOccurrenceId,
            UploadedFile::fake()->createWithContent('report-a.png', $binary),
        );
        self::assertFalse($first->duplicate);

        $duplicate = $upload->handle(
            $firstActor->playerId,
            $firstOccurrenceId,
            UploadedFile::fake()->createWithContent('report-copy.png', $binary),
        );
        self::assertTrue($duplicate->duplicate);
        self::assertSame($first->evidenceId, $duplicate->evidenceId);
        self::assertSame(1, GameEvidence::query()->where('alliance_id', $firstAllianceId)->count());

        $secondAccount = $scenario->authUser();
        $secondActor = $scenario->player((int) $secondAccount->id, 59110);
        [$secondAllianceId, $secondOccurrenceId] = $this->bearHunt($scenario, $secondActor);
        $otherAlliance = $upload->handle(
            $secondActor->playerId,
            $secondOccurrenceId,
            UploadedFile::fake()->createWithContent('same-private-report.png', $binary),
        );

        self::assertFalse($otherAlliance->duplicate);
        self::assertNotSame($first->evidenceId, $otherAlliance->evidenceId);
        $secondEvidence = GameEvidence::query()->findOrFail($otherAlliance->evidenceId);
        self::assertSame($secondAllianceId, (string) $secondEvidence->alliance_id);
        self::assertNull($secondEvidence->visual_duplicate_evidence_id);
        Queue::assertPushed(ClassifyGameEvidenceJob::class, 2);
    }

    public function test_unsafe_spoofed_and_oversized_uploads_fail_closed_without_persisting_evidence(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('evidence.disk', 'local');
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 59111);
        [, $occurrenceId] = $this->bearHunt($scenario, $actor);

        $this->bindScanner(clean: false, reason: 'Security scan rejected the screenshot.');
        $upload = app(UploadGameEvidence::class);
        try {
            $upload->handle(
                $actor->playerId,
                $occurrenceId,
                UploadedFile::fake()->createWithContent('unsafe.png', $this->pngBinary()),
            );
            self::fail('Expected unsafe evidence to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        self::assertSame(0, GameEvidence::query()->count());

        $this->bindScanner(clean: true);
        $upload = app(UploadGameEvidence::class);
        try {
            $upload->handle(
                $actor->playerId,
                $occurrenceId,
                UploadedFile::fake()->createWithContent('spoofed.png', 'this is not an image'),
            );
            self::fail('Expected spoofed evidence to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        self::assertSame(0, GameEvidence::query()->count());

        config()->set('evidence.max_kilobytes', 1);
        try {
            $upload->handle(
                $actor->playerId,
                $occurrenceId,
                UploadedFile::fake()->image('oversized.png', 32, 32)->size(2),
            );
            self::fail('Expected oversized evidence to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('evidence', $exception->errors());
        }
        self::assertSame(0, GameEvidence::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_upload_requires_current_manage_authority_before_processing(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('evidence.disk', 'local');
        $this->bindScanner(clean: true);
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->authUser();
        $owner = $scenario->player((int) $ownerAccount->id, 59112);
        [, $occurrenceId] = $this->bearHunt($scenario, $owner);
        $outsiderAccount = $scenario->authUser();
        $outsider = $scenario->player((int) $outsiderAccount->id, 59112);

        $this->expectException(AuthorizationException::class);
        app(UploadGameEvidence::class)->handle(
            $outsider->playerId,
            $occurrenceId,
            UploadedFile::fake()->createWithContent('unauthorized.png', $this->pngBinary()),
        );
    }

    /** @return array{0:string,1:string} */
    private function bearHunt(ScenarioFactory $scenario, PlayerReference $actor): array
    {
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        return [$alliance->allianceId, $created->firstOccurrenceId];
    }

    private function bindScanner(bool $clean, ?string $reason = null): void
    {
        app()->instance(UploadScanner::class, new class($clean, $reason) implements UploadScanner
        {
            public function __construct(private readonly bool $clean, private readonly ?string $reason) {}

            public function scan(UploadedFile $file): UploadScanResult
            {
                return new UploadScanResult($this->clean, $this->reason);
            }
        });
    }

    private function pngBinary(): string
    {
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/l3MB9QAAAABJRU5ErkJggg==',
            true,
        );
        self::assertIsString($binary);

        return $binary;
    }
}
