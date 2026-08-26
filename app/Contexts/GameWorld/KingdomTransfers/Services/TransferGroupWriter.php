<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransferGroupWriter
{
    public function __construct(
        private ResolveKingdom $kingdoms,
        private TransferEvidenceReferenceGuard $evidence,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<int|string> $kingdomNumbers */
    public function save(
        TransferMutationContext $context,
        string $allianceId,
        string $windowId,
        string $officialLabel,
        array $kingdomNumbers,
        TransferSourceType $sourceType,
        string $sourceReference,
        string $observedAt,
        ?string $evidenceId = null,
    ): string {
        TransferWindow::query()
            ->where('alliance_id', $allianceId)
            ->whereKey($windowId)
            ->lockForUpdate()
            ->firstOrFail();

        $label = trim($officialLabel);
        $source = trim($sourceReference);
        if ($label === '' || $source === '' || $kingdomNumbers === []) {
            throw ValidationException::withMessages([
                'group' => 'Official label, Kingdom membership and source reference are required.',
            ]);
        }
        $evidenceId = $this->evidence->assertUsable($allianceId, $sourceType, $evidenceId);

        $kingdomIds = [];
        foreach (array_values(array_unique($kingdomNumbers)) as $number) {
            $reference = $this->kingdoms->handle($number);
            if ($reference !== null) {
                $kingdomIds[] = $reference->kingdomId;
            }
        }
        sort($kingdomIds);
        if ($kingdomIds === []) {
            throw ValidationException::withMessages([
                'kingdom_numbers' => 'At least one valid Kingdom is required.',
            ]);
        }

        $otherMembership = DB::table('transfer_group_kingdoms')
            ->join('transfer_groups', 'transfer_groups.id', '=', 'transfer_group_kingdoms.transfer_group_id')
            ->where('transfer_groups.transfer_window_id', $windowId)
            ->whereNull('transfer_groups.superseded_at')
            ->whereRaw('lower(transfer_groups.official_label) <> lower(?)', [$label])
            ->whereIn('transfer_group_kingdoms.kingdom_id', $kingdomIds)
            ->exists();
        if ($otherMembership) {
            throw ValidationException::withMessages([
                'kingdom_numbers' => 'A Kingdom cannot belong to two current official Transfer Groups in the same window.',
            ]);
        }

        $observed = CarbonImmutable::parse($observedAt)->utc();
        $previous = TransferGroup::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_window_id', $windowId)
            ->whereRaw('lower(official_label) = lower(?)', [$label])
            ->whereNull('superseded_at')
            ->with('kingdoms:id')
            ->lockForUpdate()
            ->first();
        if ($previous instanceof TransferGroup) {
            $existing = $previous->kingdoms
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->sort()
                ->values()
                ->all();
            if (
                $existing === $kingdomIds
                && $previous->source_type === $sourceType
                && $previous->source_reference === $source
                && $previous->evidence_id === $evidenceId
                && $previous->observed_at->equalTo($observed)
            ) {
                return (string) $previous->id;
            }
            $previous->forceFill(['superseded_at' => CarbonImmutable::now('UTC')])->save();
        }

        $group = TransferGroup::query()->create([
            'alliance_id' => $allianceId,
            'transfer_window_id' => $windowId,
            'official_label' => $label,
            'revision' => $previous instanceof TransferGroup ? $previous->revision + 1 : 1,
            'source_type' => $sourceType,
            'source_reference' => $source,
            'observed_at' => $observed,
            'evidence_id' => $evidenceId,
            'recorded_by_player_id' => $context->actor->playerId,
        ]);
        $group->kingdoms()->sync($kingdomIds);
        $metadata = [
            'alliance_id' => $allianceId,
            'transfer_window_id' => $windowId,
            'transfer_group_id' => (string) $group->id,
            'revision' => $group->revision,
            'kingdom_count' => count($kingdomIds),
            'source_type' => $sourceType->value,
        ];
        $this->audit->record('kingdoms.transfer_group_recorded', $context->actor, $group, null, $metadata);
        $this->outbox->record('kingdoms.transfer_group_recorded', $allianceId, $group, $metadata);

        return (string) $group->id;
    }
}
