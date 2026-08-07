<?php

declare(strict_types=1);

namespace Tests\Feature\Rallies;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Rallies\Actions\AssignRallyMember;
use App\Domain\Rallies\Actions\CreateEventRecommendedFormation;
use App\Domain\Rallies\Actions\CreateRallyGroup;
use App\Domain\Rallies\Actions\CreateRallyGuidanceRule;
use App\Domain\Rallies\Actions\SaveMemberFormation;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RallyCoordinationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guidance_formations_groups_and_capacity_safe_assignments_are_tenant_scoped(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            owner: $owner,
            name: 'Rally Alliance',
            slug: 'rally-alliance',
            timezone: 'America/Toronto',
        );

        $firstJoiner = $this->activeMember($alliance->id);
        $secondJoiner = $this->activeMember($alliance->id);

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Bear Rally',
            firstLocalStart: CarbonImmutable::parse('2026-08-08 20:00', 'America/Toronto'),
            durationMinutes: 30,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();
        $composition = new FormationComposition(10, 10, 80);

        $guidance = $this->app->make(CreateRallyGuidanceRule::class)->handle(
            actor: $owner,
            alliance: $alliance,
            name: 'Bear standard',
            composition: $composition,
            effectiveFrom: CarbonImmutable::parse('2026-08-01'),
            heroRecommendations: ['Chenko', 'Yenwoo'],
            leadRequirements: 'Use the strongest available rally lead.',
            joinerGuidance: 'Join promptly with the configured formation.',
            source: 'Alliance strategy review',
            rationale: 'Archer-heavy damage profile for this activity.',
        );

        $recommended = $this->app->make(CreateEventRecommendedFormation::class)->handle(
            actor: $owner,
            alliance: $alliance,
            occurrence: $occurrence,
            name: 'Bear joiner',
            assignmentRole: RallyAssignmentRole::Joiner,
            composition: $composition,
            heroes: ['Chenko', 'Yenwoo'],
            guidanceRule: $guidance,
        );

        $group = $this->app->make(CreateRallyGroup::class)->handle(
            actor: $owner,
            alliance: $alliance,
            occurrence: $occurrence,
            name: 'Rally One',
            maxJoiners: 1,
            recommendedFormation: $recommended,
        );

        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $assign = $this->app->make(AssignRallyMember::class);

        $lead = $assign->handle($owner, $alliance, $group, $ownerMembership, RallyAssignmentRole::Lead, 1);
        $first = $assign->handle($owner, $alliance, $group, $firstJoiner, RallyAssignmentRole::Joiner);
        $second = $assign->handle($owner, $alliance, $group, $secondJoiner, RallyAssignmentRole::Joiner);

        self::assertSame(RallyAssignmentStatus::Assigned, $lead->status);
        self::assertSame(RallyAssignmentStatus::Assigned, $first->status);
        self::assertSame(RallyAssignmentStatus::Standby, $second->status);
        self::assertSame(3, RallyAssignment::query()->where('rally_group_id', $group->id)->count());

        $saved = $this->app->make(SaveMemberFormation::class)->handle(
            actor: $firstJoiner->user,
            alliance: $alliance,
            name: 'My bear formation',
            composition: $composition,
            heroes: ['Chenko'],
            isDefault: true,
        );

        self::assertSame($firstJoiner->id, $saved->membership_id);
        self::assertTrue($saved->is_default);
        self::assertSame('Alliance strategy review', $guidance->source);
        self::assertSame(80, $recommended->archer_percent);
    }

    public function test_rally_assignment_rejects_membership_from_another_alliance(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'First Rally', 'first-rally');
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherOwner, 'Other Rally', 'other-rally');

        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $owner,
            alliance: $alliance,
            title: 'Tenant Rally',
            firstLocalStart: CarbonImmutable::parse('2026-08-08 20:00', $alliance->timezone),
            durationMinutes: 30,
        );
        /** @var EventOccurrence $occurrence */
        $occurrence = $event->occurrences->sole();
        $group = $this->app->make(CreateRallyGroup::class)->handle($owner, $alliance, $occurrence, 'Main Rally');
        $otherMembership = AllianceMembership::query()
            ->where('alliance_id', $otherAlliance->id)
            ->where('user_id', $otherOwner->id)
            ->sole();

        $this->expectException(AuthorizationException::class);

        $this->app->make(AssignRallyMember::class)->handle(
            $owner,
            $alliance,
            $group,
            $otherMembership,
            RallyAssignmentRole::Joiner,
        );
    }

    private function activeMember(string $allianceId): AllianceMembership
    {
        $user = User::factory()->create();

        return AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ])->load('user');
    }
}
