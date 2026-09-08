<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\GameWorld\Progression\Enums\ProgressionReleaseStatus;
use App\Contexts\GameWorld\Progression\Exceptions\NoProgressionDatasetPublished;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use App\ReadModels\Progression\Http\Controllers\ProgressionPlannerController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\v3\TestCase;

final class ProgressionDatasetAvailabilityV3Test extends TestCase
{
    private string $originalBasePath;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = base_path();
        $this->fixturePath = sys_get_temp_dir().'/progression-availability-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->fixturePath.'/resources/data/progression');
        $this->app->setBasePath($this->fixturePath);
        app(PlayerContext::class)->activate(new PlayerReference('governor', 1, 'kingdom', 'Governor', null), 1);
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        File::deleteDirectory($this->fixturePath);

        parent::tearDown();
    }

    #[DataProvider('unavailableReleases')]
    public function test_only_absent_or_valid_unpublished_releases_produce_the_empty_planner(?ProgressionReleaseStatus $status): void
    {
        if ($status !== null) {
            $this->writeRelease('unpublished', $status);
        }

        try {
            app(ProgressionDatasetQuery::class)->latest();
            self::fail('An unavailable published release must raise typed absence.');
        } catch (NoProgressionDatasetPublished) {
            $response = $this->plannerResponse();
            $page = $response->getData(true);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('Kingdom/Progression/Planner', $page['component']);
            self::assertNull($page['props']['planner']['dataset']);
            self::assertSame([], $page['props']['planner']['families']);
            self::assertFalse($page['props']['observationAccess']['canView']);
            self::assertNull($page['props']['governor']['allianceId']);
        }
    }

    public static function unavailableReleases(): iterable
    {
        yield 'no releases' => [null];
        foreach (ProgressionReleaseStatus::cases() as $status) {
            if ($status !== ProgressionReleaseStatus::Published) {
                yield $status->value => [$status];
            }
        }
    }

    public function test_latest_selects_the_newest_published_version_without_promoting_a_candidate(): void
    {
        $this->writeRelease('older', ProgressionReleaseStatus::Published, '1.0.0');
        $this->writeRelease('published', ProgressionReleaseStatus::Published, '2.0.0');
        $this->writeRelease('candidate', ProgressionReleaseStatus::Candidate, '3.0.0');

        $query = app(ProgressionDatasetQuery::class);
        $latest = $query->latest();
        self::assertSame('published', $latest->id);
        self::assertSame($latest->checksum, $query->require($latest->id, $latest->checksum)->checksum);
    }

    #[DataProvider('invalidReleases')]
    public function test_invalid_release_integrity_is_not_rendered_as_absence(string $failure): void
    {
        $release = $this->writeRelease('invalid', ProgressionReleaseStatus::Published);
        $path = $this->fixturePath.'/resources/data/progression/invalid/';
        if ($failure === 'json') {
            file_put_contents($path.'release.json', '{broken');
        } else {
            if ($failure === 'schema') {
                $release['schema_version'] = 99;
            } elseif ($failure === 'manifest') {
                $release['files'] = ['heroes.json', 'systems.json'];
            } else {
                $release['release_status'] = 'not-a-status';
            }
            file_put_contents($path.'release.json', json_encode($release, JSON_THROW_ON_ERROR));
        }

        try {
            app(ProgressionDatasetQuery::class)->latest();
            self::fail('Invalid release content must fail validation.');
        } catch (RuntimeException $exception) {
            self::assertNotInstanceOf(NoProgressionDatasetPublished::class, $exception);
        }

        $this->expectException(RuntimeException::class);
        $this->plannerResponse();
    }

    public static function invalidReleases(): iterable
    {
        yield 'malformed JSON' => ['json'];
        yield 'unsupported schema' => ['schema'];
        yield 'missing required manifest file' => ['manifest'];
        yield 'invalid publication status' => ['status'];
    }

    public function test_changed_pinned_release_is_rejected_instead_of_falling_back_to_latest(): void
    {
        $this->writeRelease('published', ProgressionReleaseStatus::Published);
        $dataset = app(ProgressionDatasetQuery::class)->latest();
        file_put_contents($this->fixturePath.'/resources/data/progression/published/systems.json', '{"changed": true}');

        try {
            $this->plannerResponse(['dataset_id' => $dataset->id, 'dataset_checksum' => $dataset->checksum]);
            self::fail('A changed pinned dataset must not be silently replaced.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('progression_dataset_id', $exception->errors());
        }
    }

    public function test_explicit_missing_dataset_does_not_fall_back_to_the_empty_planner(): void
    {
        $this->expectException(ValidationException::class);
        $this->plannerResponse(['dataset_id' => 'missing']);
    }

    /** @param array<string,string> $parameters */
    private function plannerResponse(array $parameters = []): JsonResponse
    {
        $request = Request::create('/kingdom/progression/planner', 'GET', $parameters);
        $request->headers->set('X-Inertia', 'true');
        $this->app->instance('request', $request);
        $response = $this->app->call(app(ProgressionPlannerController::class), ['request' => $request])->toResponse($request);
        self::assertInstanceOf(JsonResponse::class, $response);

        return $response;
    }

    /** @return array<string,mixed> */
    private function writeRelease(string $id, ProgressionReleaseStatus $status, string $version = '1.0.0'): array
    {
        $path = $this->fixturePath.'/resources/data/progression/'.$id;
        File::ensureDirectoryExists($path);
        $release = [
            'id' => $id,
            'schema_version' => 2,
            'dataset_version' => $version,
            'observed_at' => '2026-09-08',
            'release_status' => $status->value,
            'sources' => [],
            'family_dispositions' => [[
                'family' => 'heroes', 'status' => 'fixture',
                'discovered_entities' => 0, 'canonical_entities' => 0,
                'reason' => 'Minimal release fixture for availability contracts.',
            ]],
            'files' => ['heroes.json', 'systems.json', 'formations.json'],
        ];
        foreach ([
            'release.json' => $release,
            'heroes.json' => ['heroes' => [], 'provenance' => []],
            'systems.json' => [],
            'formations.json' => ['formations' => []],
        ] as $file => $data) {
            file_put_contents($path.'/'.$file, json_encode($data, JSON_THROW_ON_ERROR));
        }

        return $release;
    }
}
