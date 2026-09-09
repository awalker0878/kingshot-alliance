<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Recruitment;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Recruitment\Actions\AddRecruitmentNote;
use App\Contexts\Alliance\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\MarkRecruitmentCommunicationSent;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\PrepareRecruitmentDecisionCommunication;
use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Actions\TagRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentOnboardingItem;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RecruitmentRetentionAuthorityV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string}> */
    public static function terminalWrites(): iterable
    {
        foreach (['note', 'reviewer', 'stage', 'convert', 'prepare', 'tag', 'reentry', 'merge source', 'merge target'] as $operation) {
            yield $operation => [$operation];
        }
    }

    #[DataProvider('terminalWrites')]
    public function test_owner_actions_do_not_repopulate_a_purged_candidate(string $operation): void
    {
        [$ownerId, $allianceId, $candidate, $templateId] = $this->fixture();
        $other = $this->candidate($allianceId, false);
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        self::assertNotNull($candidate->fresh()?->anonymized_at);
        $before = $this->state();
        try {
            match ($operation) {
                'note' => app(AddRecruitmentNote::class)->handle($ownerId, $allianceId, (string) $candidate->id, 'Private new note'),
                'reviewer' => app(AssignRecruitmentReviewer::class)->handle($ownerId, $allianceId, (string) $candidate->id, $ownerId),
                'stage' => app(ChangeRecruitmentStage::class)->handle($ownerId, $allianceId, (string) $candidate->id, RecruitmentStage::Screening),
                'convert' => app(ConvertAcceptedRecruitmentCandidate::class)->handle($ownerId, $allianceId, (string) $candidate->id, $ownerId),
                'prepare' => app(PrepareRecruitmentDecisionCommunication::class)->handle($ownerId, $allianceId, (string) $candidate->id, $templateId),
                'tag' => app(TagRecruitmentCandidate::class)->handle($ownerId, $allianceId, (string) $candidate->id, 'Private new tag'),
                'reentry' => app(SetRecruitmentReentryControl::class)->handle($ownerId, $allianceId, (string) $candidate->id, RecruitmentReentryControl::ReviewRequired, 'Private review'),
                'merge source' => app(MergeRecruitmentCandidates::class)->handle($ownerId, $allianceId, (string) $candidate->id, (string) $other->id),
                'merge target' => app(MergeRecruitmentCandidates::class)->handle($ownerId, $allianceId, (string) $other->id, (string) $candidate->id),
            };
            self::fail('Anonymization must remain terminal at the owner boundary.');
        } catch (ValidationException $exception) {
            self::assertSame(['This recruitment record has been anonymized.'], $exception->errors()['candidate'] ?? null);
        }
        self::assertSame($before, $this->state());
        self::assertSame(0, app(PurgeExpiredRecruitmentCandidates::class)->handle());
    }

    /** @return iterable<string,array{string,bool}> */
    public static function childOrders(): iterable
    {
        foreach (['communication', 'onboarding'] as $child) {
            yield $child.' writer first' => [$child, false];
            yield $child.' purge first' => [$child, true];
        }
    }

    #[DataProvider('childOrders')]
    public function test_child_writes_and_retention_acquire_candidate_before_child_in_both_orders(string $child, bool $purgeFirst): void
    {
        [$ownerId, $allianceId, $candidate, , $communicationId, $onboardingId] = $this->fixture();
        $table = $child === 'communication' ? 'recruitment_communications' : 'recruitment_candidate_onboarding';
        $write = fn () => $this->writeChild($child, $ownerId, $allianceId, $communicationId, $onboardingId);
        $purge = static fn () => app(PurgeExpiredRecruitmentCandidates::class)->handle();
        $primary = $this->competitor();
        $attempted = false;
        $childLocks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $candidate, $table, $purgeFirst, $write, $purge, &$attempted, &$childLocks): void {
            if (str_starts_with($query->sql, 'select * from "'.$table.'"') && str_contains($query->sql, 'for update')) {
                $childLocks[] = $query->connectionName;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "recruitment_candidates"') || ! str_contains($query->sql, $purgeFirst ? 'for update' : 'for share') || ! in_array((string) $candidate->id, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            self::assertSame([], $childLocks);
            DB::setDefaultConnection('retention_writer');
            try {
                try {
                    $purgeFirst ? $write() : $purge();
                    self::fail('The candidate lifecycle barrier must serialize its child writers.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNotContains('retention_writer', $childLocks);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $purgeFirst ? $purge() : $write();
            self::assertTrue($attempted);
            if (! $purgeFirst) {
                self::assertSame(1, $purge());
            }
            self::assertNotNull($candidate->fresh()?->anonymized_at);
            self::assertSame(0, DB::table($table)->where('candidate_id', $candidate->id)->count());
            $before = $this->state();
            try {
                $write();
                self::fail('A removed child must not be recreated on retry.');
            } catch (ModelNotFoundException) {
                self::assertSame($before, $this->state());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('retention_writer');
        }
    }

    /** @return iterable<string,array{string}> */
    public static function children(): iterable
    {
        yield 'communication' => ['communication'];
        yield 'onboarding' => ['onboarding'];
    }

    #[DataProvider('children')]
    public function test_child_routing_is_revalidated_after_candidate_discovery(string $child): void
    {
        [$ownerId, $allianceId, , , $communicationId, $onboardingId] = $this->fixture();
        $other = $this->candidate($allianceId, false);
        $table = $child === 'communication' ? 'recruitment_communications' : 'recruitment_candidate_onboarding';
        $childId = $child === 'communication' ? $communicationId : $onboardingId;
        $primary = $this->competitor();
        $changed = false;
        $afterChange = null;
        DB::listen(function (QueryExecuted $query) use ($primary, $table, $childId, $other, &$changed, &$afterChange): void {
            if ($changed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select "candidate_id" from "'.$table.'"')) {
                return;
            }
            $changed = true;
            DB::connection('retention_writer')->table($table)->where('id', $childId)->update(['candidate_id' => $other->id]);
            $afterChange = $this->state();
        });
        try {
            try {
                $this->writeChild($child, $ownerId, $allianceId, $communicationId, $onboardingId);
                self::fail('A changed candidate binding must not redirect the requested write.');
            } catch (ModelNotFoundException) {
                self::assertTrue($changed);
                self::assertSame($afterChange, $this->state());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('retention_writer');
        }
    }

    #[DataProvider('children')]
    public function test_late_child_delivery_failure_rolls_back_state_and_audit(string $child): void
    {
        [$ownerId, $allianceId, , , $communicationId, $onboardingId] = $this->fixture();
        $event = $child === 'communication' ? 'recruitment.communication.sent' : 'recruitment.onboarding.updated';
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($event, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array($event, $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected Recruitment child delivery failure.');
            }
        });
        try {
            $this->writeChild($child, $ownerId, $allianceId, $communicationId, $onboardingId);
            self::fail('Child state and audit must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Recruitment child delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $this->writeChild($child, $ownerId, $allianceId, $communicationId, $onboardingId);
        self::assertSame(1, DB::table('audit_events')->where('event', $event)->count());
    }

    public function test_late_retention_failure_restores_candidate_children_and_delivery_records(): void
    {
        [, , $candidate] = $this->fixture();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'update "recruitment_candidates"') && str_contains($query->sql, '"anonymized_at"')) {
                $failed = true;
                throw new RuntimeException('Injected final candidate anonymization failure.');
            }
        });
        try {
            app(PurgeExpiredRecruitmentCandidates::class)->handle();
            self::fail('The terminal marker and all deletion effects must commit together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected final candidate anonymization failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        self::assertNotNull($candidate->fresh()?->anonymized_at);
        self::assertSame(0, app(PurgeExpiredRecruitmentCandidates::class)->handle());
    }

    public function test_management_detail_returns_not_found_for_an_anonymized_candidate(): void
    {
        [$ownerId, , $candidate] = $this->fixture();
        $user = User::query()->findOrFail(Player::query()->findOrFail($ownerId)->user_id);
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $ownerId])
            ->get(route('alliance.recruitment.candidates.show', $candidate))->assertOk();
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        $this->get(route('alliance.recruitment.candidates.show', $candidate))->assertNotFound();
        self::assertNotNull($candidate->fresh()?->anonymized_at);
    }

    public function test_duplicate_projection_excludes_terminal_sources_and_matches(): void
    {
        [, $allianceId, $candidate] = $this->fixture();
        self::assertSame(1, app(PurgeExpiredRecruitmentCandidates::class)->handle());
        $candidate->refresh();
        $current = $this->candidate($allianceId, false);
        $current->forceFill(['email' => $candidate->email])->save();
        self::assertCount(0, app(RecruitmentDuplicateFinder::class)->forCandidate($allianceId, $current)->items);
        self::assertCount(0, app(RecruitmentDuplicateFinder::class)->forCandidate($allianceId, $candidate)->items);
    }

    private function writeChild(string $child, string $ownerId, string $allianceId, string $communicationId, string $onboardingId): void
    {
        if ($child === 'communication') {
            app(MarkRecruitmentCommunicationSent::class)->handle($ownerId, $allianceId, $communicationId);
        } else {
            app(UpdateRecruitmentOnboardingStatus::class)->handle($ownerId, $allianceId, $onboardingId, RecruitmentOnboardingStatus::Completed);
        }
    }

    /** @return array{string,string,RecruitmentCandidate,string,string,string} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59285);
        $alliance = $factory->alliance($owner);
        $candidate = $this->candidate($alliance->allianceId, true);
        $template = RecruitmentDecisionTemplate::query()->create([
            'alliance_id' => $alliance->allianceId, 'name' => 'Decision', 'decision_stage' => RecruitmentStage::Declined,
            'subject' => 'Decision for {{candidate_name}}', 'body' => 'Private decision', 'is_active' => true,
            'created_by_player_id' => $owner->playerId, 'updated_by_player_id' => $owner->playerId,
        ]);
        $communicationId = app(PrepareRecruitmentDecisionCommunication::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, (string) $template->id);
        $item = RecruitmentOnboardingItem::query()->create([
            'alliance_id' => $alliance->allianceId, 'name' => 'Introduction', 'is_active' => true,
            'created_by_player_id' => $owner->playerId, 'updated_by_player_id' => $owner->playerId,
        ]);
        $onboarding = RecruitmentCandidateOnboarding::query()->create([
            'alliance_id' => $alliance->allianceId, 'candidate_id' => $candidate->id,
            'onboarding_item_id' => $item->id, 'status' => RecruitmentOnboardingStatus::Pending,
        ]);
        app(AddRecruitmentNote::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'Private original note');
        app(AssignRecruitmentReviewer::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, $owner->playerId);
        app(TagRecruitmentCandidate::class)->handle($owner->playerId, $alliance->allianceId, (string) $candidate->id, 'Original tag');

        return [$owner->playerId, $alliance->allianceId, $candidate, (string) $template->id, $communicationId, (string) $onboarding->id];
    }

    private function candidate(string $allianceId, bool $expired): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()->create([
            'alliance_id' => $allianceId, 'full_name' => $expired ? 'Expired applicant' : 'Current applicant',
            'email' => $expired ? 'expired@example.test' : 'current@example.test',
            'stage' => $expired ? RecruitmentStage::Declined : RecruitmentStage::New,
            'submitted_at' => now()->subDays(100), 'retention_due_at' => $expired ? now()->subDay() : null,
        ]);
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.retention_writer', array_replace(DB::connection()->getConfig(), ['name' => 'retention_writer']));
        DB::connection('retention_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['recruitment_candidates', 'recruitment_answers', 'recruitment_notes', 'recruitment_candidate_reviewers', 'recruitment_tags', 'recruitment_stage_history', 'recruitment_communications', 'recruitment_candidate_onboarding', 'audit_events', 'outbox_messages', 'invitations'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        $state['recruitment_candidate_tags'] = DB::table('recruitment_candidate_tags')->orderBy('candidate_id')->orderBy('tag_id')->get()->toJson();

        return $state;
    }
}
