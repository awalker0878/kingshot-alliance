<?php

declare(strict_types=1);

namespace Tests\Feature\ReadModels\RecruitmentManagement;

use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentQuestion;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentManagementQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentCataloguePaginationV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{string,string,string,string}> */
    public static function catalogues(): iterable
    {
        yield 'questions' => ['questions', 'questionPage', 'recruitment_questions', 'position'];
        yield 'decision templates' => ['templates', 'templatePage', 'recruitment_decision_templates', 'name'];
        yield 'onboarding' => ['onboarding', 'onboardingPage', 'recruitment_onboarding_items', 'position'];
    }

    #[DataProvider('catalogues')]
    public function test_catalogues_page_independently_with_scoped_current_authority_and_bounded_hydration(string $kind, string $property, string $table, string $column): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59305);
        $alliance = $factory->alliance($owner);
        $otherOwner = $factory->player($factory->account()->userId, 59305);
        $other = $factory->alliance($otherOwner);
        foreach (self::catalogues() as [, , $seedTable]) {
            foreach ([[$owner->playerId, $alliance->allianceId], [$otherOwner->playerId, $other->allianceId]] as [$actorId, $allianceId]) {
                $rows = [];
                for ($i = 0; $i < 55; $i++) {
                    $rows[] = $this->row($seedTable, $actorId, $allianceId, $i);
                }
                DB::table($seedTable)->insert($rows);
            }
        }
        $expected = DB::table($table)->where('alliance_id', $alliance->allianceId)->orderBy($column)->orderBy('id')->pluck('id')->all();
        $hydrated = [];
        foreach ([RecruitmentQuestion::class, RecruitmentDecisionTemplate::class, RecruitmentOnboardingItem::class] as $model) {
            $model::retrieved(static function ($row) use (&$hydrated): void {
                $hydrated[] = [(string) $row->getTable(), (string) $row->alliance_id];
            });
        }
        $query = app(RecruitmentManagementQuery::class);
        $first = $query->forAlliance($owner->playerId, $alliance->allianceId);
        self::assertCount(78, $hydrated);
        self::assertSame([$alliance->allianceId], array_values(array_unique(array_column($hydrated, 1))));
        self::assertArrayNotHasKey('members', $first);
        self::assertSame(['questions' => 8, 'onboarding' => 8], $first['nextPositions']);
        self::assertCount(25, $first[$property]['items']);
        $cursor = $first[$property]['nextCursor'];
        self::assertIsString($cursor);
        $seen = array_column($first[$property]['items'], 'id');
        self::assertSame(array_slice($expected, 0, 25), $seen);
        DB::table($table)->where('id', $seen[24])->delete();
        $newRow = $this->row($table, $owner->playerId, $alliance->allianceId, 99);
        DB::table($table)->insert($newRow);
        do {
            $page = $query->forAlliance($owner->playerId, $alliance->allianceId, catalogueCursors: [$kind => $cursor]);
            self::assertFalse($page[$property]['isFirstPage']);
            self::assertLessThanOrEqual(25, count($page[$property]['items']));
            foreach (['questionPage', 'templatePage', 'onboardingPage'] as $otherProperty) {
                if ($otherProperty !== $property) {
                    self::assertSame($first[$otherProperty]['items'], $page[$otherProperty]['items']);
                    self::assertTrue($page[$otherProperty]['isFirstPage']);
                }
            }
            array_push($seen, ...array_column($page[$property]['items'], 'id'));
            $cursor = $page[$property]['nextCursor'];
        } while ($cursor !== null);
        self::assertSame([...$expected, $newRow['id']], $seen);
        self::assertCount(count($seen), array_unique($seen));
        $originalCursor = $first[$property]['nextCursor'];
        self::assertIsString($originalCursor);
        foreach ([
            fn () => $query->forAlliance($otherOwner->playerId, $other->allianceId, catalogueCursors: [$kind => $originalCursor]),
            fn () => $query->forAlliance($owner->playerId, $alliance->allianceId, catalogueCursors: [$kind === 'questions' ? 'templates' : 'questions' => $originalCursor]),
            fn () => $query->forAlliance($owner->playerId, $alliance->allianceId, catalogueCursors: [$kind => 'invalid-cursor']),
        ] as $invalid) {
            try {
                $invalid();
                self::fail('Catalogue continuation must retain its original scope.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
        DB::table('alliance_memberships')->where('alliance_id', $alliance->allianceId)->where('player_id', $owner->playerId)->update(['status' => 'suspended']);
        $this->expectException(AuthorizationException::class);
        $query->forAlliance($owner->playerId, $alliance->allianceId, catalogueCursors: [$kind => $originalCursor]);
    }

    public function test_next_position_uses_the_full_catalogue_and_never_exceeds_the_owner_limit(): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59305);
        $alliance = $factory->alliance($owner);
        foreach (['recruitment_questions', 'recruitment_onboarding_items'] as $table) {
            DB::table($table)->insert(array_replace($this->row($table, $owner->playerId, $alliance->allianceId, 1), ['position' => 65535]));
        }
        $page = app(RecruitmentManagementQuery::class)->forAlliance($owner->playerId, $alliance->allianceId);
        self::assertSame(['questions' => 65535, 'onboarding' => 65535], $page['nextPositions']);
    }

    /** @return array<string,mixed> */
    private function row(string $table, string $actorId, string $allianceId, int $index): array
    {
        $base = [
            'id' => strtolower((string) Str::ulid()), 'alliance_id' => $allianceId, 'is_active' => false,
            'created_by_player_id' => $actorId, 'updated_by_player_id' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ];

        return $base + match ($table) {
            'recruitment_questions' => ['prompt' => sprintf('Archived question %02d', $index), 'question_type' => 'short_text', 'position' => 7, 'is_required' => true],
            'recruitment_decision_templates' => ['name' => sprintf('Archived template %02d', $index), 'decision_stage' => 'accepted', 'subject' => 'Decision subject', 'body' => 'Complete decision body'],
            default => ['name' => sprintf('Archived onboarding %02d', $index), 'description' => 'Complete retained task', 'position' => 7, 'is_required' => true],
        };
    }
}
