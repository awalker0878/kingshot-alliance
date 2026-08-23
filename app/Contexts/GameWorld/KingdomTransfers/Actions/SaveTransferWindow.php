<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Actions;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferWindow
{
    public function __construct(private TransferWriteState $writeState, private TransferAuthorization $authority, private AuditRecorder $audit, private OutboxRecorder $outbox) {}

    /** @param array{label:string,pre_transfer_starts_at:string,invitational_starts_at:string,transfer_opens_at:string,ends_at:string,source_type:TransferSourceType,source_reference:string,observed_at:string,evidence_id?:string|null} $data */
    public function handle(string $allianceId, string $actorPlayerId, array $data, ?string $windowId = null): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $data, $windowId): string {
            $context = $this->writeState->lockAuthority($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, TransferPermission::Manage);
            $now = CarbonImmutable::now('UTC');
            $times = array_map(static fn (string $value): CarbonImmutable => CarbonImmutable::parse($value)->utc(), [$data['pre_transfer_starts_at'], $data['invitational_starts_at'], $data['transfer_opens_at'], $data['ends_at']]);
            if (! ($times[0]->lt($times[1]) && $times[1]->lt($times[2]) && $times[2]->lt($times[3]))) throw ValidationException::withMessages(['window' => 'Transfer phase boundaries must be strictly increasing.']);
            $label = trim($data['label']);
            $source = trim($data['source_reference']);
            if ($label === '' || $source === '') throw ValidationException::withMessages(['window' => 'Window label and source reference are required.']);

            $window = $windowId === null ? new TransferWindow(['alliance_id' => $allianceId]) : TransferWindow::query()->where('alliance_id', $allianceId)->whereKey($windowId)->lockForUpdate()->firstOrFail();
            if ($window->exists && $now->gte($window->pre_transfer_starts_at)) throw ValidationException::withMessages(['window' => 'A Transfer Window cannot be edited after Phase I starts; record sourced game-fact corrections instead.']);
            $window->forceFill(['label' => $label, 'pre_transfer_starts_at' => $times[0], 'invitational_starts_at' => $times[1], 'transfer_opens_at' => $times[2], 'ends_at' => $times[3], 'source_type' => $data['source_type'], 'source_reference' => $source, 'observed_at' => CarbonImmutable::parse($data['observed_at'])->utc(), 'evidence_id' => $data['evidence_id'] ?? null, 'recorded_by_player_id' => $actorPlayerId])->save();
            $event = $windowId === null ? 'kingdoms.transfer_window_created' : 'kingdoms.transfer_window_updated';
            $metadata = ['alliance_id' => $allianceId, 'transfer_window_id' => (string) $window->id, 'source_type' => $data['source_type']->value, 'observed_at' => $window->observed_at->toIso8601String()];
            $this->audit->record($event, $context->actor, $window, null, $metadata);
            $this->outbox->record($event, $allianceId, $window, $metadata);
            return (string) $window->id;
        });
    }
}
