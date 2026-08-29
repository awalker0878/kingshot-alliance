<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use App\ReadModels\Support\ReadModelTelemetry;

/**
 * Authorized cross-owner projection for one recruitment-to-transfer journey.
 * It persists no campaign state; every value retains its canonical owner.
 */
final readonly class TransferCampaignWorkspaceQuery
{
    public function __construct(
        private TransferAuthorization $transfers,
        private AllianceAuthorization $alliance,
        private TransferEligibilityQuery $eligibility,
    ) {}

    /** @return array<string,mixed> */
    public function forCandidate(
        string $actorPlayerId,
        string $allianceId,
        RecruitmentCandidate $candidate,
    ): array {
        $startedAt = hrtime(true);
        if (! $this->alliance->allows(
            $actorPlayerId,
            $allianceId,
            AlliancePermission::RecruitmentManage,
        )) {
            throw new AuthorizationException;
        }
        if ((string) $candidate->alliance_id !== $allianceId) {
            throw new AuthorizationException;
        }

        $base = [
            'available' => true,
            'recruitment' => [
                'stage' => $candidate->recruitmentStage()->value,
                'submittedAt' => $candidate->submitted_at->toIso8601String(),
                'nextActionAt' => $candidate->next_action_at?->toIso8601String(),
            ],
            'playerLink' => $candidate->player_id === null ? 'unlinked' : 'linked',
            'transfer' => null,
            'communications' => $this->communications($allianceId, (string) $candidate->id),
            'membership' => null,
            'ownerHrefs' => [
                'recruitment' => '/alliance/recruitment/'.(string) $candidate->id,
                'transfer' => '/alliance/transfers/manage',
                'roster' => '/alliance/members/manage',
            ],
        ];

        if ($candidate->player_id === null) {
            return $this->record($startedAt, $actorPlayerId, $allianceId, $candidate, $base);
        }

        if ($this->alliance->allows($actorPlayerId, $allianceId, AlliancePermission::View)) {
            $membership = AllianceMembership::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $candidate->player_id)
                ->orderByDesc('created_at')
                ->first();
            $roster = AllianceRosterEntry::query()
                ->where('alliance_id', $allianceId)
                ->where('player_id', $candidate->player_id)
                ->orderByDesc('last_observed_at')
                ->first();
            $base['membership'] = [
                'status' => $membership?->status?->value,
                'rank' => $membership?->rank?->value,
                'joinedAt' => $membership?->joined_at?->toIso8601String(),
                'rosterState' => $roster?->state?->value,
                'rosterObservedAt' => $roster?->last_observed_at?->toIso8601String(),
            ];
        }

        if (! $this->transfers->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            return $this->record($startedAt, $actorPlayerId, $allianceId, $candidate, [
                ...$base,
                'available' => false,
                'unavailableReason' => 'transfer_not_authorized',
            ]);
        }

        $participant = TransferParticipant::query()
            ->where('alliance_id', $allianceId)
            ->where('player_id', $candidate->player_id)
            ->with([
                'plan.window',
                'sourceKingdom:id,number',
                'destinationKingdom:id,number',
                'observations' => static fn ($query) => $query->orderByDesc('observed_at')->limit(20),
                'blockers' => static fn ($query) => $query->orderByDesc('created_at')->limit(20),
                'completion',
            ])
            ->orderByRaw('withdrawn_at IS NULL desc')
            ->orderByDesc('created_at')
            ->first();
        if (! $participant instanceof TransferParticipant) {
            return $this->record($startedAt, $actorPlayerId, $allianceId, $candidate, $base);
        }

        $plan = $participant->plan;
        $assessmentRow = $this->eligibility->forPlan(
            $allianceId,
            $plan,
            new Collection([$participant]),
        )[(string) $participant->id] ?? null;
        $assessment = is_array($assessmentRow) ? ($assessmentRow['assessment'] ?? null) : null;

        $base['transfer'] = [
            'participantId' => (string) $participant->id,
            'planId' => (string) $plan->id,
            'planLabel' => (string) $plan->label,
            'planState' => $plan->state->value,
            'direction' => $participant->direction->value,
            'readiness' => $participant->readiness_state->value,
            'sourceKingdom' => $participant->sourceKingdom?->number,
            'destinationKingdom' => $participant->destinationKingdom?->number,
            'window' => [
                'label' => (string) $plan->window->label,
                'phase' => $plan->window->phaseAt(now())->value,
                'endsAt' => $plan->window->ends_at->toIso8601String(),
                'sourceType' => $plan->window->source_type->value,
                'sourceReference' => (string) $plan->window->source_reference,
                'observedAt' => $plan->window->observed_at->toIso8601String(),
                'evidenceId' => $plan->window->evidence_id,
            ],
            'eligibility' => $assessment instanceof TransferEligibilityAssessment
                ? [
                    'outcome' => $assessment->outcome->value,
                    'evaluatedAt' => $assessment->evaluatedAt->toIso8601String(),
                    'primaryAction' => $assessment->primaryAction,
                    'requirements' => array_map(
                        static fn (TransferRequirement $requirement): array => [
                            'key' => $requirement->key->value,
                            'state' => $requirement->state->value,
                            'explanation' => $requirement->explanation,
                            'sourceType' => $requirement->sourceType?->value,
                            'sourceReference' => $requirement->sourceReference,
                            'observedAt' => $requirement->observedAt?->toIso8601String(),
                            'validUntil' => $requirement->validUntil?->toIso8601String(),
                        ],
                        $assessment->requirements,
                    ),
                ]
                : null,
            'evidence' => $participant->observations->map(static fn ($observation): array => [
                'id' => (string) $observation->id,
                'kind' => $observation->kind->value,
                'sourceType' => $observation->source_type->value,
                'sourceReference' => (string) $observation->source_reference,
                'observedAt' => $observation->observed_at->toIso8601String(),
                'validUntil' => $observation->valid_until?->toIso8601String(),
                'evidenceId' => $observation->evidence_id,
            ])->values()->all(),
            'activeBlockers' => $participant->blockers
                ->filter(static fn ($blocker): bool => $blocker->state === TransferBlockerState::Active)
                ->map(static fn ($blocker): array => [
                    'id' => (string) $blocker->id,
                    'summary' => (string) $blocker->summary,
                ])->values()->all(),
            'completion' => $participant->completion === null
                ? null
                : [
                    'completedAt' => $participant->completion->completed_at->toIso8601String(),
                    'rosterEntryId' => $participant->completion->roster_entry_id,
                ],
            'withdrawnAt' => $participant->withdrawn_at?->toIso8601String(),
        ];

        return $this->record($startedAt, $actorPlayerId, $allianceId, $candidate, $base);
    }

    /**
     * @param  array<string,mixed>  $projection
     * @return array<string,mixed>
     */
    private function record(
        int $startedAt,
        string $actorPlayerId,
        string $allianceId,
        RecruitmentCandidate $candidate,
        array $projection,
    ): array {
        $transfer = is_array($projection['transfer'] ?? null) ? $projection['transfer'] : [];
        $communications = is_array($projection['communications'] ?? null) ? $projection['communications'] : [];
        ReadModelTelemetry::record('transfer_campaign.rendered', $startedAt, [
            'actor_player_id' => $actorPlayerId,
            'alliance_id' => $allianceId,
            'candidate_id' => (string) $candidate->id,
            'target_player_id' => is_string($candidate->player_id) ? $candidate->player_id : null,
            'participant_id' => is_string($transfer['participantId'] ?? null) ? $transfer['participantId'] : null,
        ], [
            'communication_count' => (int) ($communications['total'] ?? 0),
            'blocker_count' => is_array($transfer['activeBlockers'] ?? null)
                ? count($transfer['activeBlockers'])
                : 0,
            'evidence_count' => is_array($transfer['evidence'] ?? null)
                ? count($transfer['evidence'])
                : 0,
        ], array_values(array_filter([
            (string) ($projection['playerLink'] ?? ''),
            ($projection['available'] ?? false) === true ? 'available' : 'unavailable',
            is_string($projection['unavailableReason'] ?? null) ? $projection['unavailableReason'] : null,
            is_string($transfer['readiness'] ?? null) ? $transfer['readiness'] : null,
        ], static fn (?string $code): bool => is_string($code) && $code !== '')));

        return $projection;
    }

    /** @return array{total:int,latestStatus:?string,latestAt:?string} */
    private function communications(string $allianceId, string $candidateId): array
    {
        $rows = RecruitmentCommunication::query()
            ->where('alliance_id', $allianceId)
            ->where('candidate_id', $candidateId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        $latest = $rows->first();

        return [
            'total' => $rows->count(),
            'latestStatus' => $latest instanceof RecruitmentCommunication
                ? $latest->communicationStatus()->value
                : null,
            'latestAt' => $latest?->sent_at?->toIso8601String() ?? $latest?->created_at?->toIso8601String(),
        ];
    }
}
