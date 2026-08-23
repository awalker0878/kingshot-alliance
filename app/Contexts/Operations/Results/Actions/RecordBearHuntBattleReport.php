<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventCapabilityGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Models\BearHuntBattleReportEntry;
use App\Contexts\Operations\Results\Models\BearHuntResultBaseline;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Services\BearHuntResultProjector;
use App\Contexts\Operations\Results\ValueObjects\BearHuntBattleReportReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordBearHuntBattleReport
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
        private EventParticipantAuthorization $participants,
        private EventPlayerContextFreezer $contexts,
        private BearHuntResultProjector $projector,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<array{player_id:string,reported_rank?:int|null,damage_points:int}> $entries */
    public function handle(
        string $actorPlayerId,
        string $occurrenceId,
        string $sourceEvidenceId,
        string $sourceCommitAttemptId,
        string $idempotencyKey,
        string $reportFingerprint,
        ?string $reportTimestampText,
        array $entries,
    ): BearHuntBattleReportReceipt {
        $this->validateCommand($sourceEvidenceId, $sourceCommitAttemptId, $idempotencyKey, $reportFingerprint, $reportTimestampText, $entries);

        return DB::transaction(function () use ($actorPlayerId, $occurrenceId, $sourceEvidenceId, $sourceCommitAttemptId, $idempotencyKey, $reportFingerprint, $reportTimestampText, $entries): BearHuntBattleReportReceipt {
            $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, (string) $route->event_id);
            $this->authorization->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::Results);
            if ($context->event->scopeEnum() !== EventScope::Alliance || $context->target->allianceId === null) {
                throw ValidationException::withMessages(['event' => 'Screenshot Intake supports Alliance Bear Hunt Events only.']);
            }
            $eventType = EventType::query()->select(['id', 'slug'])->whereKey($context->event->event_type_id)->sharedLock()->firstOrFail();
            if ($eventType->slug !== 'bear-hunt') {
                throw ValidationException::withMessages(['event' => 'This occurrence is not a Bear Hunt Event.']);
            }
            $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id)->lockForUpdate()->firstOrFail();

            $existing = BearHuntBattleReport::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing instanceof BearHuntBattleReport) {
                if ((string) $existing->occurrence_id !== $occurrenceId || $existing->report_fingerprint !== $reportFingerprint) {
                    throw ValidationException::withMessages(['idempotency_key' => 'The idempotency key was already used for a different Bear Hunt report.']);
                }
                $playerResults = $this->projector->recompute($occurrenceId, $actorPlayerId);
                $metadata = [
                    'event_id' => (string) $context->event->id,
                    'occurrence_id' => $occurrenceId,
                    'report_id' => (string) $existing->id,
                    'source_evidence_id' => $sourceEvidenceId,
                    'source_commit_attempt_id' => $sourceCommitAttemptId,
                    'actor_player_id' => $actorPlayerId,
                ];
                $this->audit->record('bear_hunt.battle_report_replayed', $context->actor, $existing, $context->target->allianceId, $metadata);
                $this->outbox->record('bear_hunt.battle_report_replayed', $context->target->allianceId, $existing, $metadata, partitionKey: $context->target->partitionKey());

                return new BearHuntBattleReportReceipt(
                    (string) $existing->id,
                    (int) BearHuntBattleReportEntry::query()->where('report_id', $existing->id)->count(),
                    true,
                    $playerResults,
                );
            }
            $fingerprintCollision = BearHuntBattleReport::query()->where('occurrence_id', $occurrenceId)->where('report_fingerprint', $reportFingerprint)->lockForUpdate()->first();
            if ($fingerprintCollision instanceof BearHuntBattleReport) {
                throw ValidationException::withMessages(['report' => 'This Bear Hunt battle report has already been committed.']);
            }

            $normalized = [];
            $seen = [];
            foreach ($entries as $entry) {
                $playerId = trim((string) $entry['player_id']);
                if ($playerId === '' || isset($seen[$playerId])) {
                    throw ValidationException::withMessages(['entries' => 'Every report entry requires one unique Governor.']);
                }
                $damage = (int) $entry['damage_points'];
                $rank = array_key_exists('reported_rank', $entry) && $entry['reported_rank'] !== null ? (int) $entry['reported_rank'] : null;
                if ($damage < 0 || ($rank !== null && ($rank < 1 || $rank > 999))) {
                    throw ValidationException::withMessages(['entries' => 'Bear Hunt damage/rank values are invalid.']);
                }
                $player = $context->actor->playerId === $playerId ? $context->actor : $this->players->lockCurrent($playerId);
                $activePresence = $this->roster->lockActiveRosterPresence($context->target->allianceId, $playerId);
                if (! $this->participants->eligibleAgainstTarget($context->target, $player, $activePresence)) {
                    throw ValidationException::withMessages(['entries' => 'A reviewed Governor is no longer eligible for this Bear Hunt.']);
                }
                $frozen = $this->contexts->existing((string) $occurrence->id, $playerId);
                if (! $frozen instanceof EventPlayerContext) {
                    $this->contexts->freeze($occurrence, $player);
                }
                $baseline = BearHuntResultBaseline::query()->where('occurrence_id', $occurrenceId)->where('player_id', $playerId)->lockForUpdate()->first();
                if (! $baseline instanceof BearHuntResultBaseline) {
                    $current = EventPlayerResult::query()->where('occurrence_id', $occurrenceId)->where('player_id', $playerId)->lockForUpdate()->first();
                    BearHuntResultBaseline::query()->create([
                        'occurrence_id' => $occurrenceId,
                        'player_id' => $playerId,
                        'source_event_player_result_id' => $current instanceof EventPlayerResult ? (string) $current->id : null,
                        'baseline_score' => $current?->score,
                        'baseline_rank' => $current?->rank,
                        'captured_at' => now(),
                    ]);
                }
                $normalized[] = ['player_id' => $playerId, 'reported_rank' => $rank, 'damage_points' => $damage];
                $seen[$playerId] = true;
            }

            $report = BearHuntBattleReport::query()->create([
                'occurrence_id' => $occurrenceId,
                'source_evidence_id' => $sourceEvidenceId,
                'source_commit_attempt_id' => $sourceCommitAttemptId,
                'idempotency_key' => $idempotencyKey,
                'report_fingerprint' => $reportFingerprint,
                'report_timestamp_text' => $reportTimestampText,
                'status' => BearHuntBattleReportStatus::Accepted,
                'recorded_by_player_id' => $actorPlayerId,
                'recorded_at' => now(),
            ]);
            foreach ($normalized as $entry) {
                BearHuntBattleReportEntry::query()->create(['report_id' => $report->id, ...$entry]);
            }
            $playerResults = $this->projector->recompute($occurrenceId, $actorPlayerId);
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => $occurrenceId,
                'report_id' => (string) $report->id,
                'source_evidence_id' => $sourceEvidenceId,
                'entry_count' => count($normalized),
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record('bear_hunt.battle_report_recorded', $context->actor, $report, $context->target->allianceId, $metadata);
            $this->outbox->record('bear_hunt.battle_report_recorded', $context->target->allianceId, $report, $metadata, partitionKey: $context->target->partitionKey());

            return new BearHuntBattleReportReceipt((string) $report->id, count($normalized), false, $playerResults);
        });
    }

    /** @param list<array{player_id:string,reported_rank?:int|null,damage_points:int}> $entries */
    private function validateCommand(string $sourceEvidenceId, string $sourceCommitAttemptId, string $idempotencyKey, string $reportFingerprint, ?string $timestamp, array $entries): void
    {
        foreach ([$sourceEvidenceId, $sourceCommitAttemptId] as $id) {
            if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $id) !== 1) {
                throw ValidationException::withMessages(['source' => 'Evidence source identifiers must be ULIDs.']);
            }
        }
        if (preg_match('/^[a-f0-9]{64}$/', $idempotencyKey) !== 1 || preg_match('/^[a-f0-9]{64}$/', $reportFingerprint) !== 1) {
            throw ValidationException::withMessages(['report' => 'Bear Hunt report keys must be lowercase SHA-256 values.']);
        }
        if ($timestamp !== null && mb_strlen(trim($timestamp)) > 64) {
            throw ValidationException::withMessages(['report_timestamp' => 'Report timestamp text must be 64 characters or fewer.']);
        }
        if ($entries === [] || count($entries) > 100) {
            throw ValidationException::withMessages(['entries' => 'A Bear Hunt report must contain between 1 and 100 reviewed Governors.']);
        }
    }
}
