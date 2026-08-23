<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEvidenceReferenceGuard;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferGroup
{
    public function __construct(
        private TransferWriteState $writeState,
        private TransferAuthorization $authority,
        private ResolveKingdom $kingdoms,
        private TransferEvidenceReferenceGuard $evidence,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array{official_label:string,kingdom_numbers:list<int|string>,source_type:TransferSourceType,source_reference:string,observed_at:string,evidence_id?:string|null} $data */
    public function handle(string $allianceId, string $actorPlayerId, string $windowId, array $data): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $windowId, $data): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            TransferWindow::query()->where('alliance_id', $allianceId)->whereKey($windowId)->lockForUpdate()->firstOrFail();
            $label = trim($data['official_label']);
            $source = trim($data['source_reference']);
            if ($label === '' || $source === '' || $data['kingdom_numbers'] === []) {
                throw ValidationException::withMessages(['group' => 'Official label, Kingdom membership and source reference are required.']);
            }
            $evidenceId = $this->evidence->assertUsable(
                $allianceId,
                $data['source_type'],
                $data['evidence_id'] ?? null,
            );
            $kingdomIds = [];
            foreach (array_values(array_unique($data['kingdom_numbers'])) as $number) {
                $ref = $this->kingdoms->handle($number);
                if ($ref !== null) {
                    $kingdomIds[] = $ref->kingdomId;
                }
            }
            sort($kingdomIds);
            $otherMembership = DB::table('transfer_group_kingdoms')->join('transfer_groups', 'transfer_groups.id', '=', 'transfer_group_kingdoms.transfer_group_id')->where('transfer_groups.transfer_window_id', $windowId)->whereNull('transfer_groups.superseded_at')->whereRaw('lower(transfer_groups.official_label) <> lower(?)', [$label])->whereIn('transfer_group_kingdoms.kingdom_id', $kingdomIds)->exists();
            if ($otherMembership) {
                throw ValidationException::withMessages(['kingdom_numbers' => 'A Kingdom cannot belong to two current official Transfer Groups in the same window.']);
            }
            $previous = TransferGroup::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $windowId)->whereRaw('lower(official_label) = lower(?)', [$label])->whereNull('superseded_at')->with('kingdoms:id')->lockForUpdate()->first();
            if ($previous instanceof TransferGroup) {
                $existing = $previous->kingdoms->pluck('id')->map(static fn ($id): string => (string) $id)->sort()->values()->all();
                if ($existing === $kingdomIds && $previous->source_type === $data['source_type'] && $previous->source_reference === $source && $previous->evidence_id === $evidenceId && $previous->observed_at->equalTo(CarbonImmutable::parse($data['observed_at'])->utc())) {
                    return (string) $previous->id;
                }
                $previous->forceFill(['superseded_at' => CarbonImmutable::now('UTC')])->save();
            }
            $group = TransferGroup::query()->create(['alliance_id' => $allianceId, 'transfer_window_id' => $windowId, 'official_label' => $label, 'revision' => $previous instanceof TransferGroup ? $previous->revision + 1 : 1, 'source_type' => $data['source_type'], 'source_reference' => $source, 'observed_at' => CarbonImmutable::parse($data['observed_at'])->utc(), 'evidence_id' => $evidenceId, 'recorded_by_player_id' => $actorPlayerId]);
            $group->kingdoms()->sync($kingdomIds);
            $metadata = ['alliance_id' => $allianceId, 'transfer_window_id' => $windowId, 'transfer_group_id' => (string) $group->id, 'revision' => $group->revision, 'kingdom_count' => count($kingdomIds), 'source_type' => $data['source_type']->value];
            $this->audit->record('kingdoms.transfer_group_recorded', $context->actor, $group, null, $metadata);
            $this->outbox->record('kingdoms.transfer_group_recorded', $allianceId, $group, $metadata);

            return (string) $group->id;
        });
    }
}
